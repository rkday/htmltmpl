
""" 
    htmltmpl
    ========
    A templating engine for separation of code and HTML.

    The documentation of this templating engine is separated to two parts:
    
        1. Description of the HTML::Template templating language.
           It's maintained in XHTML format.
           
        2. Documentation of this module that provides a Python implementation
           of the HTML::Template language. It's maintained in XHTML format.
    
    All the documentation can be found in 'doc' directory of the
    distribution tarball or at the homepage of the engine:
    
        http://htmltmpl.sourceforge.net/

    Latest versions of this module are also available at that website.

    You can use and redistribute this module under conditions of the
    GNU General Public License that can be found either at http://www.gnu.org/
    or in file "LICENSE" contained in the distribution tarball of this module.

    Copyright (c) 2001 Tomas Styblo, tripie@cpan.org
    Prague, the Czech Republic
"""

__version__ = 1.13
__author__ = "Tomas Styblo (tripie@cpan.org)"

# All imported modules are part of standard Python library.

from types import *
import re
import os
import os.path
import pprint       # only for debugging
import sys
import copy
import cgi          # for HTML escaping of variables
import urllib       # for URL escaping of variables
import cPickle      # for template compilation


##############################################
#               CLASS: Template              #
##############################################

class Template:   

    def __init__(self, template, template_path, 
                 html_escape=1, global_vars=0, include=1, strict_case=1,
                 max_include=5, compile=1, cache=0, blind_cache=0, 
                 comments=1, magic_vars=1, debug=0):
                
        if not template_path:
            raise TemplateError, "Template path must not be empty."
        if type(template_path) is not ListType:
            raise TemplateError, "Template path must be a list."
        if blind_cache: cache = 1

        self._template = template                   # template filename
        self._template_path = template_path         # list of paths

        # Save the optional parameters.
        # These values are not modified by any method.
        self._html_escape = html_escape
        self._global_vars = global_vars
        self._include = include
        self._strict_case = strict_case
        self._max_include = max_include
        self._compile = compile
        self._cache = cache
        self._blind_cache = blind_cache
        self._comments = comments
        self._magic_vars = magic_vars
        self._debug = debug

        # Resolved path to the main template. It's never modified.
        self._main_file = self.find(template)

        # Memory cache of metadata of the template.
        # It's modified only by the create_cached() method.
        self._cached = None

        # This is a counter of current inclusion depth.
        # It's modified only by the include_templates() method.
        self._include_level = 0     # cuurent inclusion depth

        # This is a list of resolved paths to all included templates.
        # It's modified by the read_file() method, which appends paths to it
        # and by the compile_template() method which resets it.
        self._include_files = []    # full paths of all included templates

        # Data structure containing variables and loops set by the
        # application.
        # Use debug=1, process some template and then check stderr to
        # see how the structure looks.
        # It's modified only by the __setitem__() and reset() methods.
        self._vars = {}        
        self.DEB("INIT DONE")
    
    def __setitem__(self, var, value):
        """ Parameters: var, value
            Set toplevel template variable 'var' representing either
            an ordinary variable or a reference to (potentially deeply nested)
            loop data to 'value'. The 'value' must be either a scalar or
            a list.
        """
        if self.is_ordinary_var(value):
            if self._strict_case and not var.islower():
                raise TemplateError, "Invalid variable name '%s'." % var
        elif type(value) is ListType:
            if self._strict_case and var != var.capitalize():
                raise TemplateError, "Invalid loop name '%s'." % var
        else:
            raise TemplateError, "Value of toplevel variable '%s' must "\
                                 "be either a scalar or a list." % var
        self._vars[var] = value
        self.DEB("VALUE SET: " + str(var))
        
    def output(self):
        """ No parameters.
            Process the template and return the result.
        """
        self.DEB("APP INPUT:")
        if self._debug: pprint.pprint(self._vars, sys.stderr)

        if self._cache:
            self.DEB("TRYING CACHED")
            tokens = self.use_cached()
        elif self._compile:
            self.DEB("TRYING COMPILED")
            tokens = self.use_compiled()
        else:
            self.DEB("USING NORMAL")
            tokens = self.compile_template()
        return self.proc_compiled(tokens)

    def reset(self):
        """ No parameters.
            Reset the template data. This method must be called before
            another processing of the template.
        """
        self._vars.clear()
        self.DEB("RESET")

    ##############################################
    #              PRIVATE METHODS               #
    ##############################################

    def DEB(self, str):
        if self._debug: print >> sys.stderr, str

    def read_file(self, filename, included):
        data = None
        for path in self._template_path:
            path_join = os.path.join(path, filename)
            if os.path.isfile(path_join):
                self.DEB("READING: " + path_join)
                try:
                    try:
                        f = open(path_join)               
                        data = f.read()
                    except IOError, (errno, errstr):
                        raise TemplateError, "IO error while reading "\
                                             "template '%s': (%d) %s"\
                                             % (path_join, errno, errstr)
                    else:
                        if included: self._include_files.append(path_join)
                        break
                finally:
                    if f: f.close()
        else:                
            # The file was not found in any directory in the template path.
            raise TemplateError, "Template '%s' cannot be found in the "\
                                 "template path." % filename
        return data

    def find(self, filename):
        for path in self._template_path:
            path_join = os.path.join(path, filename)
            if os.path.isfile(path_join):
                self.DEB("FOUND TEMPLATE: " + path_join)
                return path_join
        else:
            # The file was not found in any directory in the template path.
            raise TemplateError, "Template '%s' cannot be found in the "\
                                 "template path." % filename

    def use_cached(self):
        if self._cached and self._blind_cache:
            # We have the template in cache and we do not care if it is
            # uptodate.
            self.DEB("CACHE: BLIND")
            pass
        elif self._cached:
            # We have the template in cache, but we must check if it
            # is uptodate.
            if self._cached.is_uptodate():
                self.DEB("CACHE: NORMAL")
                pass
            else:
                self.DEB("CACHE: UPDATE")
                self.create_cached()
        else:
            self.DEB("CACHE: CREATING")
            self.create_cached()
        return self._cached.tokens()

    def create_cached(self):
        if self._compile:
            tokens = self.use_compiled()
        else:
            tokens = self.compile_template()
        self._cached = Metadata(__version__, self._main_file,
                                self._include_files, tokens, self._debug)

    def use_compiled(self):
        if self.is_compiled():
            compiled = self.load_compiled()
            compiled.debug(self._debug)
            if compiled.is_uptodate():
                self.DEB("COMPILED: UPTODATE")
                return compiled.tokens()
            else:
                self.DEB("COMPILED: NOT UPTODATE")
        else:
            self.DEB("COMPILED: NEW")

        # We have to recompile the template.
        tokens = self.compile_template()
        new_compiled = Metadata(__version__, self._main_file, 
                                self._include_files, tokens, self._debug)
        self.save_compiled(new_compiled)
        return tokens

    def compile_template(self):
        """ Tokenize the template data. Return a list of tokens. """
        MAIN_TEMPLATE = 0
        del self._include_files[:]
        template_data = self.read_file(self._template, MAIN_TEMPLATE)
        preproc_data = self.preproc_template(template_data)
        return map(self.merge_statements, self.parse_template(preproc_data))

    def merge_statements(self, token):
        """ Convert '<!-- TMPL_*' tokens to '<TMPL_*' tokens.
            This is optimization, because then we can only test for
            '<TMPL_*' in proc_compiled().
        """
        if token and (token.startswith("<!-- TMPL_") or \
                      token.startswith("<!-- /TMPL_")):
            ret_token = "<" + token[5:]
            self.DEB("MERGING TOKEN: '%s' => '%s'" % (token, ret_token))
        else:
            ret_token = token
        return ret_token
                
    def preproc_template(self, template_data):
        """ Remove comments from the template.
            Process recursively all TMPL_INCLUDEs in the template.
        """
        data = template_data        
        if self._comments:
            self.DEB("PREPROC: COMMENTS")
            data = self.remove_comments(data)
        if self._include:
            self.DEB("PREPROC: INCLUDES")
            data = self.include_templates(data)
        return data

    def remove_comments(self, template_data):
        pattern = r"### .*$"
        rc = re.compile(pattern, re.MULTILINE)
        return rc.sub("", template_data)
           
    def include_templates(self, template_data):
        INCLUDED_TEMPLATE = 1
        INCLUDE_PARAMS = 1
        PARAM_NAME = 1
        pattern = r"""
            (?:^[ \t]+)?               # eat spaces, tabs (opt.)
            (<
             (?:!--[ ])?               # comment start + space (opt.)
             TMPL_INCLUDE              # statement
            )
            (?:\s+(?:NAME="?)?([-\w._:/\\]+)"?)?  # filename (opt.)
            (?:[ ]--)?                 # space + comment end (opt.)
            >
            [%s]?                      # eat trailing newline (opt.)
        """ % os.linesep
        rc = re.compile(pattern, re.VERBOSE | re.MULTILINE)
        tokens = rc.split(template_data)
        len_tokens = len(tokens)
        i = 0
        out = ""
        skip_params = 0
        
        # Process the list of tokens.
        while 1:
            if i == len_tokens: break
            if skip_params:
                skip_params = 0
                i += INCLUDE_PARAMS
                continue

            token = tokens[i]
            if token == "<TMPL_INCLUDE" or token == "<!-- TMPL_INCLUDE":
                filename = tokens[i + PARAM_NAME]
                if not filename:
                    raise TemplateError, "No filename in <TMPL_INCLUDE>."
                skip_params = 1
                self._include_level += 1
                if self._include_level > self._max_include:
                    # Max include level reached, append a warning.
                    out += """
                        <p>
                        <strong>HTMLTMPL WARNING:</strong><br />
                        Maximum include limit reached.<br />
                        Cannot include template: <strong>%s</strong>
                        </p>
                    """ % filename
                    self.DEB("INCLUDE: LIMIT: " + filename)
                else:
                    data = self.read_file(filename, INCLUDED_TEMPLATE)
                    out += self.preproc_template(data)
                    self.DEB("INCLUDE: " + filename)
            else:
                # Template data.
                out += token                        
            i += 1
            # end of the main while loop
        if self._include_level > 0: self._include_level -= 1
        return out
    
    def parse_template(self, template_data):
        """
        Split the template data into tokens separated by template statements.
        The statements and associated parameters are also separately 
        included in the resulting list of tokens.
        """
        self.DEB("PARSING TEMPLATE")
        pattern = r"""
            (?:^[ \t]+)?               # eat spaces, tabs (opt.)
            (<
             (?:!--[ ])?               # comment start + space (opt.)
             /?TMPL_[A-Z]+             # closing slash "/" (opt.) + statement
            )                               
            (?:\s+(?:NAME="?)?(\w+)"?)?     # variable name (opt.)
            (?:\s+ESCAPE="?(\w+)"?)?   # escape mode override (opt.)
            (?:\s+GLOBAL="?(\w+)"?)?   # global_vars override (opt.)
            (?:[ ]--)?                 # space + comment end (opt.)
            >
            [%s]?                      # eat trailing newline (opt.)
        """ % os.linesep
        rc = re.compile(pattern, re.VERBOSE | re.MULTILINE)
        return rc.split(template_data)
    
    def proc_compiled(self, tokens):
        # Total number of parameters in regexp in parse_template().
        # Increment if adding a parameter to any statement.
        PARSE_PARAMS = 3

        # Relative positions of parameters in regexp in parse_template().
        PARAM_NAME = 1
        PARAM_ESCAPE = 2
        PARAM_GLOBAL = 3

        # This flag means "jump behind the end of current statement" or
        # "skip the parameters of current statement".
        # Even parameters that actually are not present in the template
        # do appear in the list of tokens as empty items !
        skip_params = 0 

        # Stack for enabling or disabling output in response to TMPL_IF,
        # TMPL_UNLESS, TMPL_ELSE and TMPL_LOOPs with no passes.
        output = []
        ENABLE_OUTPUT = 1
        DISABLE_OUTPUT = 0
        
        # Stacks for data related to loops.
        loop_name = []        # name of a loop
        loop_pass = []        # current pass of a loop (counted from zero)
        loop_start = []       # index of loop start in token list
        loop_total = []       # total number of passes in a loop
        
        len_tokens = len(tokens)
        out = ""

        # Process the list of tokens.
        i = 0
        while 1:
            if i == len_tokens: break            
            if skip_params:   
                # Skip the parameters following a statement.
                skip_params = 0
                i += PARSE_PARAMS
                continue

            token = tokens[i]
            if token.startswith("<TMPL_") or \
               token.startswith("</TMPL_"):
                if token == "<TMPL_VAR":
                    # TMPL_VARs should be first. They are most common.
                    var = tokens[i + PARAM_NAME]
                    if not var:
                        raise TemplateError, "No identifier in <TMPL_VAR>."
                    escape = tokens[i + PARAM_ESCAPE]
                    globalp = tokens[i + PARAM_GLOBAL]
                    skip_params = 1
                    
                    # If output of current block is not disabled then append
                    # the substitued and escaped variable to the output.
                    if DISABLE_OUTPUT not in output:
                        value = str(self.find_value(var, loop_name, loop_pass,
                                                    loop_total, globalp))
                        out += self.escape(value, escape)
                        self.DEB("VAR: " + str(var))

                elif token == "<TMPL_LOOP":
                    var = tokens[i + PARAM_NAME]
                    if not var:
                        raise TemplateError, "No identifier in <TMPL_LOOP>."
                    skip_params = 1

                    # Find total number of passes in this loop.
                    passtotal = self.find_value(var, loop_name, loop_pass,
                                                loop_total)
                    if not passtotal: passtotal = 0
                    # Push data for this loop on the stack.
                    loop_total.append(passtotal)
                    loop_start.append(i)
                    loop_pass.append(0)
                    loop_name.append(var)

                    # Disable output of loop block if the number of passes
                    # in this loop is zero.
                    if passtotal == 0:
                        # This loop is empty.
                        output.append(DISABLE_OUTPUT)
                        self.DEB("LOOP: DISABLE: " + str(var))
                    else:
                        output.append(ENABLE_OUTPUT)
                        self.DEB("LOOP: FIRST PASS: %s TOTAL: %d"\
                                 % (var, passtotal))

                elif token == "<TMPL_IF":
                    var = tokens[i + PARAM_NAME]
                    if not var:
                        raise TemplateError, "No identifier in <TMPL_IF>."
                    globalp = tokens[i + PARAM_GLOBAL]
                    skip_params = 1
                    if not self.find_value(var, loop_name, loop_pass,
                                          loop_total, globalp):
                        output.append(DISABLE_OUTPUT)
                        self.DEB("IF: DISABLE: " + str(var))
                    else:
                        output.append(ENABLE_OUTPUT)
                        self.DEB("IF: ENABLE: " + str(var))
     
                elif token == "<TMPL_UNLESS":
                    var = tokens[i + PARAM_NAME]
                    if not var:
                        raise TemplateError, "No identifier in <TMPL_UNLESS>."
                    globalp = tokens[i + PARAM_GLOBAL]
                    skip_params = 1
                    if self.find_value(var, loop_name, loop_pass,
                                      loop_total, globalp):
                        output.append(DISABLE_OUTPUT)
                        self.DEB("UNLESS: DISABLE: " + str(var))
                    else:
                        output.append(ENABLE_OUTPUT)
                        self.DEB("UNLESS: ENABLE: " + str(var))
     
                elif token == "</TMPL_LOOP":
                    skip_params = 1
                    if not loop_name:
                        raise TemplateError, "Unmatched </TMPL_LOOP>."
                    
                    # If this loop was not disabled, then record the pass.
                    if loop_total[-1] > 0: loop_pass[-1] += 1
                    
                    if loop_pass[-1] == loop_total[-1]:
                        # There are no more passes in this loop. Pop
                        # the loop from stack.
                        loop_pass.pop()
                        loop_name.pop()
                        loop_start.pop()
                        loop_total.pop()
                        output.pop()
                        self.DEB("LOOP: END")
                    else:
                        # Jump to the beggining of this loop block 
                        # to process next pass of the loop.
                        i = loop_start[-1]
                        self.DEB("LOOP: NEXT PASS")
     
                elif token == "</TMPL_IF":
                    skip_params = 1
                    if not output:
                        raise TemplateError, "Unmatched </TMPL_IF>."
                    output.pop()
                    self.DEB("IF: END")
     
                elif token == "</TMPL_UNLESS":
                    skip_params = 1
                    if not output:
                        raise TemplateError, "Unmatched </TMPL_UNLESS>."
                    output.pop()
                    self.DEB("UNLESS: END")
     
                elif token == "<TMPL_ELSE":
                    skip_params = 1
                    if not output:
                        raise TemplateError, "Unmatched <TMPL_ELSE>."
                    if output[-1] == DISABLE_OUTPUT:
                        # Condition was false, activate the ELSE block.
                        output[-1] = ENABLE_OUTPUT
                        self.DEB("ELSE: ENABLE")
                    elif output[-1] == ENABLE_OUTPUT:
                        # Condition was true, deactivate the ELSE block.
                        output[-1] = DISABLE_OUTPUT
                        self.DEB("ELSE: DISABLE")
                    else:
                        raise TemplateError, "BUG: ELSE: INVALID FLAG"
 
                else:
                    raise TemplateError, "Invalid statement %s>." % token
                     
            elif DISABLE_OUTPUT not in output:
                # If output of current block is not disabled, then 
                # append template data to the output buffer.
                out += token
                
            i += 1
            # end of the big while loop
        
        # Check whether all opening statements were closed.
        if loop_name: raise TemplateError, "Missing </TMPL_LOOP>."
        if output: raise TemplateError, "Missing </TMPL_IF> or </TMPL_UNLESS>"
        return out

    def find_value(self, var, loop_name, loop_pass, loop_total,
                   global_override=""):
        """ Search the self._vars data structure to find variable var
            located in currently processed pass of a loop which
            is currently being processed. If the variable is an ordinary
            variable, then return it.
            
            If the variable is an identificator of a loop, then 
            return the total number of times this loop will
            be executed.
            
            Return an empty string, if the variable is not
            found at all.
        """
        # Search for the requested variable in magic vars if the name
        # of the variable starts with "__" and if we are inside a loop.
        if self._magic_vars and var.startswith("__") and loop_name:
            return self.magic_var(var, loop_pass[-1], loop_total[-1])
                    
        # Search for an ordinary variable or for a loop.
        # Recursively search in self._vars for the requested variable.
        scope = self._vars
        globals = []
        for i in range(len(loop_name)):            
            # If global_vars is on then push the value on the stack.
            if self.is_global_on(global_override) and scope.has_key(var) and \
               self.is_ordinary_var(scope[var]):
                globals.append(scope[var])
            
            # Descent deeper into the hierarchy.
            if scope.has_key(loop_name[i]) and scope[loop_name[i]]:
                scope = scope[loop_name[i]][loop_pass[i]]
            else:
                self.DEB("FIND: NO LOOP: " + str(var))
                return ""
            
        if scope.has_key(var):
            if type(scope[var]) is ListType:
                # The requested value is a loop.
                # Return total number of its passes.
                self.DEB("FIND: LOOP: " + str(var))
                return len(scope[var])
            else:
                self.DEB("FIND: VAR: " + str(var))
                return scope[var]
        elif globals and self.is_global_on(global_override):
            self.DEB("FIND: GLOBAL: " + str(var))
            return globals.pop()
        else:
            self.DEB("FIND: NO VAR: " + str(var))
            if self._strict_case and var[0].isupper():
                # This is a loop name.
                # Return zero, because the user wants to know number
                # of its passes.
                # XX: This does not work if strict_case is false
                # and loop names are not capitalized.
                return 0
            else:
                return ""

    def magic_var(self, var, loop_pass, loop_total):
        self.DEB("MAGIC: '%s', PASS: %d, TOTAL: %d"\
                 % (var, loop_pass, loop_total))
        if var == "__FIRST__":
            if loop_pass == 0:
                return 1
            else:
                return 0
        elif var == "__LAST__":
            if loop_pass == loop_total - 1:
                return 1
            else:
                return 0
        elif var == "__INNER__":
            # If this is neither the first nor the last pass.
            if loop_pass != 0 and loop_pass != loop_total - 1:
                return 1
            else:
                return 0        
        elif var == "__PASS__":
            # Magic variable __PASS__ counts passes from one.
            return loop_pass + 1
        elif var == "__PASSTOTAL__":
            return loop_total
        elif var == "__ODD__":
            # Internally pass numbers stored in loop_pass are counted from
            # zero. But the template language presents them counted from one.
            # Therefore we must add one to the actual loop_pass value to get
            # the value we present to the user.
            if (loop_pass + 1) % 2 != 0:
                return 1
            else:
                return 0
        elif var.startswith("__EVERY__"):
            # Magic variable __EVERY__x is never true in first or last pass.
            if loop_pass != 0 and loop_pass != loop_total - 1:
                # Check if an integer follows the variable name.
                try:
                    every = int(var[9])   # nine is length of "__EVERY__"
                except ValueError:
                    raise TemplateError, "Magic variable __EVERY__x: "\
                                         "Invalid pass number."
                else:
                    if not every:
                        raise TemplateError, "Magic variable __EVERY__x: "\
                                             "Pass number cannot be zero."
                    elif (loop_pass + 1) % every == 0:
                        self.DEB("MAGIC: EVERY: " + str(every))
                        return 1
                    else:
                        return 0
        else:
            raise TemplateError, "Invalid magic variable '%s'." % var

    def escape(self, str, override=""):
        ESCAPE_QUOTES = 1
        if (self._html_escape and override != "NONE" and override != "0" and \
            override != "URL") or override == "HTML" or override == "1":
            return cgi.escape(str, ESCAPE_QUOTES)
        elif override == "URL":
            return urllib.quote_plus(str)
        else:
            return str

    def is_ordinary_var(self, var):
        if type(var) == StringType or type(var) == IntType or \
           type(var) == LongType or type(var) == FloatType:
            return 1
        else:
            return 0

    def is_global_on(self, override=""):
        if (self._global_vars and override != "0") or override == "1":
            return 1
        else:
            return 0

    def is_compiled(self):
        cfilename = self._main_file + "c"   # "template.tmplc"
        if os.path.isfile(cfilename):
            self.DEB("COMPILE: COMPILED")
            return 1
        else:
            self.DEB("COMPILE: NOT COMPILED")
            return 0
        
    def load_compiled(self):
        cfilename = self._main_file + "c"   # "template.tmplc"
        self.DEB("LOADING META")
        try:
            try:
                cfile = open(cfilename)
                metadata = cPickle.load(cfile)
            except IOError, (errno, errstr):
                raise TemplateError, "IO error while loading compiled "\
                                     "template '%s': (%d) %s"\
                                     % (cfilename, errno, errstr)
            except cPickle.PicklingError, error:
                raise TemplateError, "Pickling error while loading compiled "\
                                     "template '%s': %s" % (cfilename, error)
            else:
                return metadata
        finally:
            if cfile: cfile.close()

    def save_compiled(self, metadata):
        cfilename = self._main_file + "c"   # creates "template.tmplc"

        # First check if we have write permission on the template's directory.
        # (On UNIX having write permission to a file does not mean
        # that we can remove the file - this is why we must check that
        # before the os.remove() in the 'finally:' code below is executed.)

        if not os.access(os.path.dirname(cfilename), os.W_OK):
            raise TemplateError, "Cannot save compiled template: write "\
                                 "permission denied on template directory."

        remove_bad = 0
        try:
            try:
                cfile = open(cfilename, "w")   # may truncate existing file
                BINARY = 1
                READABLE = 0
                if self._debug:
                    cPickle.dump(metadata, cfile, READABLE)
                else:
                    cPickle.dump(metadata, cfile, BINARY)
            except IOError, (errno, errstr):
                remove_bad = 1
                raise TemplateError, "IO error while saving compiled "\
                                     "template '%s': (%d) %s"\
                                      % (cfilename, errno, errstr)
            except cPickle.PicklingError, error:
                remove_bad = 1
                raise TemplateError, "Pickling error while saving "\
                                     "compiled template '%s': %s"\
                                     % (cfilename, error)
            else:
                self.DEB("META SAVE OK")
        finally:
            if cfile: cfile.close()
            if remove_bad and os.path.isfile(cfilename):
                # X: We may lose the original exception here, raising OSError.
                os.remove(cfilename)


##############################################
#               CLASS: Metadata              #
##############################################

class Metadata:
    """ This class provides storage and methods for the compiled template
        and associated metadata.
    """
    
    def __init__(self, version, main_file, include_files, tokens, debug):
        self._version = version
        self._main_file = main_file
        self._tokens = tokens
        self._mtimes = {}
        self._debug = debug

        if os.path.isfile(main_file):
            self._mtimes[main_file] = os.path.getmtime(main_file)
        else:
            raise TemplateError, "Metadata: file does not exist: '%s'"\
                                 % main_file

        for inc_file in include_files:
            if os.path.isfile(inc_file):
                self._mtimes[inc_file] = os.path.getmtime(inc_file)
            else:
                raise TemplateError, "Metadata: file does not exist: '%s'"\
                                     % inc_file

    def __getstate__(self):
        dict = copy.copy(self.__dict__)
        del dict["_debug"]
        return dict

    def __setstate__(self, dict):
        dict["_debug"] = 0
        self.__dict__ = dict

    def is_uptodate(self):
        if self._version != __version__:
            self.DEB("META: FALSE: __version__")
            return 0
    
        # Check modification times of the main template and all included
        # templates. If the included template no longer exists, then
        # the problem will be resolved when the template is recompiled.
        for tmplfile in self._mtimes.keys():
            if not (os.path.isfile(tmplfile) and \
                    self._mtimes[tmplfile] == os.path.getmtime(tmplfile)):
                self.DEB("META: FALSE: MTIME: " + tmplfile)
                return 0        
        else:
            # Metadata is uptodate.
            self.DEB("META: UPTODATE")
            return 1       
    
    def tokens(self):
        return self._tokens

    def debug(self, debug):
        self._debug = debug

    def DEB(self, str):
        if self._debug: print >> sys.stderr, str


##############################################
#                EXCEPTIONS                  #
##############################################

class TemplateError(Exception):
    def __init__(self, error):
        Exception.__init__(self, "Htmltmpl error: " + error)

