
""" A templating engine for separation of code and HTML.

    The documentation of this templating engine is separated to two parts:
    
        1. Description of the HTML::Template templating language.
           
        2. Documentation of classes and API of this module that provides
           a Python implementation of the HTML::Template language.
    
    All the documentation can be found in 'doc' directory of the
    distribution tarball or at the homepage of the engine.
    Latest versions of this module are also available at that website.

    You can use and redistribute this module under conditions of the
    GNU General Public License that can be found either at
    [ http://www.gnu.org/ ] or in file "LICENSE" contained in the
    distribution tarball of this module.

    Copyright (c) 2001 Tomas Styblo, tripie@cpan.org

    @name           htmltmpl
    @version        1.15
    @author-name    Tomas Styblo
    @author-email   tripie@cpan.org
    @website        http://htmltmpl.sourceforge.net/
    @license-name   GNU GPL
    @license-url    http://www.gnu.org/licenses/gpl.html
"""

__version__ = 1.15
__author__ = "Tomas Styblo (tripie@cpan.org)"

# All imported modules are part of the standard Python library.

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

INCLUDE_DIR = "inc"

# Find a way to lock files. Currently implemented only for UNIX and windows.
LOCKTYPE_FCNTL = 1
LOCKTYPE_MSVCRT = 2
LOCKTYPE = None
try:
    import fcntl
except:
    try:
        import msvcrt
    except:
        LOCKTYPE = None
    else:
        LOCKTYPE = LOCKTYPE_MSVCRT
else:
    LOCKTYPE = LOCKTYPE_FCNTL
LOCK_EX = 1
LOCK_SH = 2
LOCK_UN = 3

##############################################
#          CLASS: TemplateManager            #
##############################################

class TemplateManager:
    """  Class that manages compilation and precompilation of templates.
    
         You should use this class whenever you work with templates
         that are stored in a file. The class can create a compiled
         template and transparently manage its precompilation. It also
         keeps the precompiled templates up-to-date by modification times
         comparisons. 
    """

    def __init__(self, include=1, max_include=5, precompile=1, comments=1,
                 debug=0):
        """ Constructor.
        
            @header
            __init__(include=1, max_include=5, precompile=1, comments=1,
                     debug=0)
            
            @param include Enable or disable included templates.
            This optional parameter can be used to enable or disable
            <em>TMPL_INCLUDE</em> inclusion of templates. Disabling of
            inclusion can improve performance a bit. The inclusion is
            enabled by default.
      
            @param max_include Maximum depth of nested inclusions.
            This optional parameter can be used to specify maximum depth of
            nested <em>TMPL_INCLUDE</em> inclusions. It defaults to 5.
            This setting prevents infinite recursive inclusions.
            
            @param precompile Enable or disable precompilation of templates.
            This optional parameter can be used to enable or disable
            creation and usage of precompiled templates.
      
            A precompiled template is saved to the same directory in
            which the main template file is located. You need write
            permissions to that directory.

            Precompilation provides a significant performance boost because
            it's not necessary to parse the templates over and over again.
            The boost is especially noticeable when templates that include
            other templates are used.
            
            Comparison of modification times of the main template and all
            included templates is used to ensure that the precompiled
            templates are up-to-date. Templates are also recompiled if the
            htmltmpl module is updated.

            The <em>TemplateError</em>exception is raised when the precompiled
            template cannot be saved. Precompilation is enabled by default.

            Precompilation is available only on UNIX and Windows platforms,
            because proper file locking which is necessary to ensure
            multitask safe behaviour is platform specific and is not
            implemented for other platforms. Attempts to enable precompilation
            on the other platforms result in raise of the
            <em>TemplateError</em> exception.
            
            @param comments Enable or disable template comments.
            This optional parameter can be used to enable or disable
            template comments.
            Disabling of the comments can improve performance a bit.
            Comments are enabled by default.
            
            @param debug Enable or disable debugging messages.
            This optional parameter is a flag that can be used to enable
            or disable debugging messages which are printed to the standard
            error output. The debugging messages are disabled by default.            
        """
        # Save the optional parameters.
        # These values are not modified by any method.
        self._include = include
        self._max_include = max_include
        self._precompile = precompile
        self._comments = comments
        self._debug = debug

        # Find what module to use to lock files.
        # File locking is necessary for the 'precompile' feature to be
        # multitask/thread safe. Currently it works only on UNIX
        # and Windows. Anyone willing to implement it on Mac ?
        if precompile and not LOCKTYPE:
                raise TemplateError, "Template precompilation is not "\
                                     "available on this platform."
        self.DEB("INIT DONE")

    def prepare(self, file):
        """ Preprocess, parse, tokenize and compile the template.
            
            If precompilation is enabled then this method tries to load
            a precompiled form of the template from the same directory
            in which the template source file is located. If it succeeds,
            then it compares modification times stored in the precompiled
            form to modification times of source files of the template,
            including source files of all templates included via the
            <em>TMPL_INCLUDE</em> statements. If any of the modification times
            differs, then the template is recompiled and the precompiled
            form updated.
            
            If precompilation is disabled, then this method parses and
            compiles the template.
            
            @header prepare(file)
            
            @return Compiled template.
            The methods returns an instance of the <em>Template</em> class
            which is a compiled form of the template. This instance can be
            used as input for the <em>TemplateProcessor</em>.
            
            @param file Path to the template file to prepare.
            The method looks for the template file in current directory
            if the parameter is a relative path. All included templates must
            be placed in subdirectory <strong>'inc'</strong> of the 
            directory in which the main template file is located.
        """
        compiled = None
        if self._precompile:
            # Check if we have write permission to the template's directory.
            if not os.access(os.path.dirname(os.path.abspath(file)), os.W_OK):
                raise TemplateError, "Cannot save precompiled templates: "\
                                     "write permission denied."
            if self.is_precompiled(file):
                try:
                    precompiled = self.load_precompiled(file)
                except PrecompiledError, template:
                    print >> sys.stderr, "Htmltmpl: bad precompiled "\
                                         "template '%s' removed" % template
                    compiled = self.compile(file)
                    self.save_precompiled(compiled)
                else:
                    precompiled.debug(self._debug)
                    compile_params = (self._include, self._max_include,
                                      self._comments)
                    if precompiled.is_uptodate(compile_params):
                        self.DEB("PRECOMPILED: UPTODATE")
                        compiled = precompiled
                    else:
                        self.DEB("PRECOMPILED: NOT UPTODATE")
                        compiled = self.update(precompiled)
            else:
                self.DEB("PRECOMPILED: NOT PRECOMPILED")
                compiled = self.compile(file)
                self.save_precompiled(compiled)
        else:
            self.DEB("PRECOMPILATION DISABLED")
            compiled = self.compile(file)
        return compiled
    
    def update(self, template):
        """ Update (recompile) a compiled template.
        
            This method recompiles a template compiled from a file.
            If precompilation is enabled then the precompiled form saved on
            disk is also updated.
            
            @header update(template)
            
            @return Recompiled template.
            It's ensured that the returned template is up-to-date.
            
            @param template A compiled template.
            This parameter should be an instance of the <em>Template</em>
            class, created either by the <em>TemplateManager</em> or by the
            <em>TemplateCompiler</em>. The instance must represent a template
            compiled from a file on disk.
        """
        self.DEB("UPDATE")
        updated = self.compile(template.file())
        if self._precompile:
            self.save_precompiled(updated)
        return updated

    ##############################################
    #              PRIVATE METHODS               #
    ##############################################    

    def DEB(self, str):
        """ Print debugging message to stderr if debugging is enabled. 
            @hidden
        """
        if self._debug: print >> sys.stderr, str

    def lock_file(self, file, lock):
        """ Provide platform independent file locking.
            @hidden
        """
        fd = file.fileno()
        if LOCKTYPE == LOCKTYPE_FCNTL:
            if lock == LOCK_SH:
                fcntl.flock(fd, fcntl.LOCK_SH)
            elif lock == LOCK_EX:
                fcntl.flock(fd, fcntl.LOCK_EX)
            elif lock == LOCK_UN:
                fcntl.flock(fd, fcntl.LOCK_UN)
            else:
                raise TemplateError, "BUG: bad lock in lock_file"
        elif LOCKTYPE == LOCKTYPE_MSVCRT:
            if lock == LOCK_SH:
                # msvcrt does not support shared locks :-(
                msvcrt.locking(fd, msvcrt.LK_LOCK, 1)
            elif lock == LOCK_EX:
                msvcrt.locking(fd, msvcrt.LK_LOCK, 1)
            elif lock == LOCK_UN:
                msvcrt.locking(fd, msvcrt.LK_UNLCK, 1)
            else:
                raise TemplateError, "BUG: bad lock in lock_file"
        else:
            raise TemplateError, "BUG: bad locktype in lock_file"

    def compile(self, file):
        """ Compile the template.
            @hidden
        """
        return TemplateCompiler(self._include, self._max_include,
                                self._comments, self._debug).compile(file)
    
    def is_precompiled(self, file):
        """ Return true if the template is already precompiled on the disk.
            @hidden
        """
        filename = file + "c"   # "template.tmplc"
        if os.path.isfile(filename):
            return 1
        else:
            return 0
        
    def load_precompiled(self, file):
        """ Load precompiled template from disk.

            Remove the precompiled template file and raise PrecompiledError
            exception if it contains corrupted or unpicklable data.
            
            @hidden
        """
        filename = file + "c"   # "template.tmplc"
        self.DEB("LOADING PRECOMPILED")
        try:
            remove_bad = 0
            file = None
            try:
                file = open(filename)
                self.lock_file(file, LOCK_SH)
                precompiled = cPickle.load(file)
            except IOError, (errno, errstr):
                raise TemplateError, "IO error in load precompiled "\
                                     "template '%s': (%d) %s"\
                                     % (filename, errno, errstr)
            except cPickle.UnpicklingError:
                remove_bad = 1
                raise PrecompiledError, filename
            except:
                remove_bad = 1
                raise
            else:
                return precompiled
        finally:
            if file:
                self.lock_file(file, LOCK_UN)
                file.close()
            if remove_bad and os.path.isfile(filename):
                # X: We may lose the original exception here, raising OSError.
                os.remove(filename)
                
    def save_precompiled(self, template):
        """ Save compiled template and to disk in precompiled form.
            
            Associated metadata is also saved. It includes: filename of the
            main template file, modification time of the main template file,
            modification times of all included templates and version of the
            htmltmpl module which compiled the template.
            
            The method removes a file which is saved only partially because
            of some error.
            
            @hidden
        """
        filename = template.file() + "c"   # creates "template.tmplc"
        try:
            remove_bad = 0
            file = None
            try:
                file = open(filename, "w")   # may truncate existing file
                self.lock_file(file, LOCK_EX)
                BINARY = 1
                READABLE = 0
                if self._debug:
                    cPickle.dump(template, file, READABLE)
                else:
                    cPickle.dump(template, file, BINARY)
            except IOError, (errno, errstr):
                remove_bad = 1
                raise TemplateError, "IO error while saving precompiled "\
                                     "template '%s': (%d) %s"\
                                      % (filename, errno, errstr)
            except cPickle.PicklingError, error:
                remove_bad = 1
                raise TemplateError, "Pickling error while saving "\
                                     "precompiled template '%s': %s"\
                                     % (filename, error)
            except:
                remove_bad = 1
                raise
            else:
                self.DEB("SAVING PRECOMPILED")
        finally:
            if file:
                self.lock_file(file, LOCK_UN)
                file.close()
            if remove_bad and os.path.isfile(filename):
                # X: We may lose the original exception here, raising OSError.
                os.remove(filename)


##############################################
#          CLASS: TemplateProcessor          #
##############################################

class TemplateProcessor:
    """ """

    def __init__(self, html_escape=1, magic_vars=1, global_vars=0, debug=0):
        """ Constructor.

            @header __init__(html_escape=1, magic_vars=1, global_vars=0,
                             debug=0)

            @param html_escape Enable or disable HTML escaping of variables.
            This optional parameter is a flag that can be used to enable or
            disable automatic HTML escaping of variables.
            All variables are by default automatically HTML escaped. 
            The escaping process substitutes HTML brackets, ampersands and
            double quotes with appropriate HTML entities.
            
            @param magic_vars Enable or disable loop magic variables.
            This parameter can be used to enable or disable
            "magic" context variables, that are automatically defined inside
            loops. Magic variables are enabled by default.

            Refer to the language specification for description of these
            magic variables.
      
            @param global_vars Globally activate global lookup of variables.
            This optional parameter is a flag that can be used to specify
            whether variables which cannot be found in the current scope
            should be automatically looked up in enclosing scopes.

            Automatic global lookup is disabled by default. Global lookup
            can be overriden on a per-variable basis by the
            <strong>GLOBAL</strong> parameter of a <strong>TMPL_VAR</strong>
            statement.

            @param debug Enable or disable debugging messages.
        """
        self._html_escape = html_escape
        self._global_vars = global_vars       
        self._magic_vars = magic_vars
        self._debug = debug        

        # Data structure containing variables and loops set by the
        # application. Use debug=1, process some template and
        # then check stderr to see how the structure looks.
        # It's modified only by set() and reset() methods.
        self._vars = {}        

    def set(self, var, value):
        """ Associate a value with top-level template variable or loop.

            A template identifier can represent either an ordinary variable
            (string) or a loop.

            To assign a value to a string identifier pass a scalar
            as the 'value' parameter. This scalar will be automatically
            converted to string.

            To assign a value to a loop identifier pass a list of mappings as the
            'value' parameter. The engine iterates over this list and assigns
            values from the mappings to variables in a template loop block if a key
            in the mapping corresponds to a name of a variable in the loop block.
            The number of mappings contained in this list is equal to number of times
            the loop block is repeated in the output.
      
            @header set(var, value)
            @return No return value.

            @param var Name of template variable or loop.
            @param value The value to associate.
            
        """
        if self.is_ordinary_var(value):
            if not var.islower():
                raise TemplateError, "Invalid variable name '%s'." % var
        elif type(value) == ListType:
            if var != var.capitalize():
                raise TemplateError, "Invalid loop name '%s'." % var
        else:
            raise TemplateError, "Value of toplevel variable '%s' must "\
                                 "be either a scalar or a list." % var
        self._vars[var] = value
        self.DEB("VALUE SET: " + str(var))
        
    def reset(self):
        """ Reset the template data.

            This method resets the data contained in the template processor
            instance. The template processor instance can be used to process
            any number of templates.

            @header reset()
            @return No return value.             
        """
        self._vars.clear()
        self.DEB("RESET")

    def process(self, template):
        """ Process a compiled template. Return the result as string.

            This method actually processes a template and returns
            the result.

            @header process(template)
            @return Result of the processing as string.

            @param template A compiled template.
            Value of this parameter must be an instance of the
            <em>Template</em> class created either by the
            <em>TemplateManager</em> or by the <em>TemplateCompiler</em>.
        """
        self.DEB("APP INPUT:")
        self.DEB(pprint.pformat(self._vars))
        
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
        
        tokens = template.tokens()
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

    ##############################################
    #              PRIVATE METHODS               #
    ##############################################

    def DEB(self, str):
        """ Print debugging message to stderr if debugging is enabled.
            @hidden
        """
        if self._debug: print >> sys.stderr, str

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

            @hidden
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
            if type(scope[var]) == ListType:
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
            if var[0].isupper():
                # This is a loop name.
                # Return zero, because the user wants to know number
                # of its passes.
                return 0
            else:
                return ""

    def magic_var(self, var, loop_pass, loop_total):
        """ Resolve and return value of a magic variable.
            Raise an exception if the magic variable is not recognized.

            @hidden
        """
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
        """ Escape a string.
            @hidden
        """
        ESCAPE_QUOTES = 1
        if (self._html_escape and override != "NONE" and override != "0" and \
            override != "URL") or override == "HTML" or override == "1":
            return cgi.escape(str, ESCAPE_QUOTES)
        elif override == "URL":
            return urllib.quote_plus(str)
        else:
            return str

    def is_ordinary_var(self, var):
        """ Return true if var is a scalar. (not a reference to loop)
            @hidden
        """
        if type(var) == StringType or type(var) == IntType or \
           type(var) == LongType or type(var) == FloatType:
            return 1
        else:
            return 0

    def is_global_on(self, override=""):
        """ Return true if global vars is enabled.
            @hidden
        """
        if (self._global_vars and override != "0") or override == "1":
            return 1
        else:
            return 0


##############################################
#          CLASS: TemplateCompiler           #
##############################################

class TemplateCompiler:
    """ Preprocess, parse, tokenize and compile the template.

        This class parses the template and produces a 'compiled' form
        of it. This compiled form is an instance of the <em>Template</em>
        class. The compiled form is used as input for the TemplateProcessor
        which uses it to actually process the template.

        This class should be used direcly only when you need to compile
        a template from a string. If your template is in a file, then you
        should use the <em>TemplateManager</em> class which provides
        a higher level interface to this class and also can save the
        compiled template to disk in a precompiled form.
    """

    def __init__(self, include=1, max_include=5, comments=1, debug=0):
        """ Constructor.

        @header __init__(include=1, max_include=5, comments=1, debug=0)

        @param include Enable or disable included templates.
        @param max_include Maximum depth of nested inclusions
        @param comments Enable or disable template comments.
        @param debug Enable or disable debugging messages.
        """
        
        self._include = include
        self._max_include = max_include
        self._comments = comments
        self._debug = debug
        
        # This is a list of filenames of all included templates.
        # It's modified by the include_templates() method.
        self._include_files = []

        # This is a counter of current inclusion depth. It's used to prevent
        # infinite recursive includes.
        self._include_level = 0
    
    def compile(self, file):
        """ Compile template from a file.

            @header compile(file)
            @return Compiled template.
            The return value is an instance of the <em>Template</em>
            class.

            @param file Filename of the template.
            See the <em>prepare()</em> method of the <em>TemplateManager</em>
            class for exaplanation of this parameter.
        """
        
        self.DEB("COMPILING FROM FILE: " + file)
        self._include_path = os.path.join(os.path.dirname(file), INCLUDE_DIR)
        preprocessed = self.preprocess(self.read(file))
        tokens = map(self.merge_statements, self.parse(preprocessed))
        compile_params = (self._include, self._max_include, self._comments)
        return Template(__version__, file, self._include_files,
                        tokens, compile_params, self._debug)

    def compile_string(self, data):
        """ Compile template from a string.

            This method compiles a template from a string. The
            template cannot include any templates.
            <strong>TMPL_INCLUDE</strong> statements are ignored.

            @header compile_string(data)
            @return Compiled template.
            The return value is an instance of the <em>Template</em>
            class.

            @param data String containing the template data.        
        """
        self.DEB("COMPILING FROM STRING")
        self._include = 0
        preprocessed = self.preprocess(data)
        tokens = map(self.merge_statements, self.parse(preprocessed))
        compile_params = (self._include, self._max_include, self._comments)
        return Template(__version__, None, None, tokens, compile_params,
                        self._debug)

    ##############################################
    #              PRIVATE METHODS               #
    ##############################################
                
    def DEB(self, str):
        """ Print debugging message to stderr if debugging is enabled.
            @hidden
        """
        if self._debug: print >> sys.stderr, str
    
    def read(self, filename):
        """ Read content of file and return it.
            @hidden
        """
        self.DEB("READING: " + filename)
        try:
            f = None
            try:
                f = open(filename)
                data = f.read()
            except IOError, (errno, errstr):
                raise TemplateError, "IO error while reading template '%s': "\
                                     "(%d) %s" % (filename, errno, errstr)
            else:
                return data
        finally:
            if f: f.close()

    def merge_statements(self, token):
        """ Convert '&lt;!-- TMPL_*' tokens to '&lt;TMPL_*' tokens.
            This is optimization, because then we can only test for
            '<TMPL_*' in process().

            @hidden
        """
        if token and (token.startswith("<!-- TMPL_") or \
                      token.startswith("<!-- /TMPL_")):
            ret_token = "<" + token[5:]
            self.DEB("MERGING TOKEN: '%s' => '%s'" % (token, ret_token))
        else:
            ret_token = token
        return ret_token
                
    def preprocess(self, data):
        """ Preprocess the template. Record paths to all included
            templates.
            @hidden
        """
        if self._comments:
            self.DEB("PREPROCESS: COMMENTS")
            data = self.remove_comments(data)
        if self._include:
            self.DEB("PREPROCESS: INCLUDES")
            data = self.include_templates(data)
        return data

    def remove_comments(self, data):
        """ Remove comments from the template.
            @hidden
        """
        pattern = r"### .*$"
        rc = re.compile(pattern, re.MULTILINE)
        return rc.sub("", data)
           
    def include_templates(self, data):
        """ Process TMPL_INCLUDE statements. Use the include_level counter
            to prevent infinite recursion.
            @hidden
        """
        INCLUDED_TEMPLATE = 1
        INCLUDE_PARAMS = 1
        PARAM_NAME = 1
        pattern = r"""
            (?:^[ \t]+)?               # eat spaces, tabs (opt.)
            (<
             (?:!--[ ])?               # comment start + space (opt.)
             TMPL_INCLUDE              # statement
            )
            (?:\s+(?:NAME="?)?([-\w.:/\\]+)"?)?  # filename (opt.)
            (?:[ ]--)?                 # space + comment end (opt.)
            >
            [%s]?                      # eat trailing newline (opt.)
        """ % os.linesep
        rc = re.compile(pattern, re.VERBOSE | re.MULTILINE)
        tokens = rc.split(data)
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
                    include_file = os.path.join(self._include_path, filename)
                    self._include_files.append(include_file)
                    data = self.read(include_file)
                    out += self.preprocess(data)
                    self.DEB("INCLUDE: " + filename)
            else:
                # Template data.
                out += token                        
            i += 1
            # end of the main while loop
        if self._include_level > 0: self._include_level -= 1
        return out
    
    def parse(self, template_data):
        """ Split the template into tokens separated by template statements.
            The statements and associated parameters are also separately 
            included in the resulting list of tokens. Return the list of
            tokens.

            @hidden
        """
        self.DEB("PARSING TEMPLATE")
        pattern = r"""
            (?:^[ \t]+)?               # eat spaces, tabs (opt.)
            (<
             (?:!--[ ])?               # comment start + space (opt.)
             /?TMPL_[A-Z]+             # closing slash "/" (opt.) + statement
            )                               
            (?:\s+(?:NAME="?)?([-\w]+)"?)?     # variable name (opt.)
            (?:\s+ESCAPE="?(\w+)"?)?   # escape mode override (opt.)
            (?:\s+GLOBAL="?(\w+)"?)?   # global_vars override (opt.)
            (?:[ ]--)?                 # space + comment end (opt.)
            >
            [%s]?                      # eat trailing newline (opt.)
        """ % os.linesep
        rc = re.compile(pattern, re.VERBOSE | re.MULTILINE)
        return rc.split(template_data)


##############################################
#              CLASS: Template               #
##############################################

class Template:
    """ This class represents a compiled template.

        This class provides storage and methods for the compiled template
        and associated metadata. It's serialized by pickle if we need to
        save the compiled template to disk in a precompiled form.

        You should never instantiate this class directly. Always use the
        <em>TemplateManager</em> or <em>TemplateCompiler</em> classes to
        create the instances of this class.

        The only method which you can directly use is the <em>is_uptodate</em>
        method.
    """
    
    def __init__(self, version, file, include_files, tokens, compile_params,
                 debug=0):
        """ Constructor.
            @hidden
        """
        self._version = version
        self._file = file
        self._tokens = tokens
        self._compile_params = compile_params
        self._debug = debug
        self._mtime = None        
        self._include_mtimes = {}

        if not file:
            self.DEB("TEMPLATE COMPILED FROM A STRING")
            return
            
        if os.path.isfile(file):
            self._mtime = os.path.getmtime(file)
        else:
            raise TemplateError, "Template: file does not exist: '%s'" % file

        for inc_file in include_files:
            if os.path.isfile(inc_file):
                self._include_mtimes[inc_file] = os.path.getmtime(inc_file)
            else:
                raise TemplateError, "Template: file does not exist: '%s'"\
                                     % inc_file
        self.DEB("NEw TMPLATE CREATED")

    def is_uptodate(self, compile_params=None):
        """ Check whether the compiled template is uptodate.

            Returns true if this compiled template is uptodate.
            Returns false, if the template source file was changed on the
            disk since it was compiled.
            Works by comparison of modification times.
            Also takes modification times of all included templates
            into account.

            @header is_uptodate(compile_params=None)
            @return True if the template is uptodate, false otherwise.

            @param compile_params Only for internal use.
            Do not use this optional parameter. It's intended only for
            internal use by the <em>TemplateManager</em>.
        """
        if not self._file:
            self.DEB("TEMPLATE COMPILED FROM A STRING")
            return 0
        
        if self._version != __version__:
            self.DEB("TEMPLATE: VERSION NOT UPTODATE")
            return 0

        if compile_params != None and compile_params != self._compile_params:
            self.DEB("TEMPLATE: DIFFERENT COMPILATION PARAMS")
            return 0
    
        # Check modification times of the main template and all included
        # templates. If the included template no longer exists, then
        # the problem will be resolved when the template is recompiled.
        if not (os.path.isfile(self._file) and \
                self._mtime == os.path.getmtime(self._file)):
            self.DEB("TEMPLATE: NOT UPTODATE: " + self._file)
            return 0        

        for inc_file in self._include_mtimes.keys():
            if not (os.path.isfile(inc_file) and \
                    self._include_mtimes[inc_file] == \
                    os.path.getmtime(inc_file)):
                self.DEB("TEMPLATE: NOT UPTODATE: " + inc_file)
                return 0
        else:
            self.DEB("TEMPLATE: UPTODATE")
            return 1       
    
    def tokens(self):
        """ Get tokens.
            @hidden
        """
        return self._tokens

    def file(self):
        """ Get file.
            @hidden
        """
        return self._file

    def debug(self, debug):
        """ Get debugging state.
            @hidden
        """
        self._debug = debug

    ##############################################
    #              PRIVATE METHODS               #
    ##############################################

    def __getstate__(self):
        """ Used by pickle when the class is serialized.
            Remove the 'debug' attribute before serialization.
            @hidden
        """
        dict = copy.copy(self.__dict__)
        del dict["_debug"]
        return dict

    def __setstate__(self, dict):
        """ Used by pickle when the class is unserialized.
            Add the 'debug' attribute.
            @hidden
        """
        dict["_debug"] = 0
        self.__dict__ = dict


    def DEB(self, str):
        """ Print debugging message to stderr.
            @hidden
        """
        if self._debug: print >> sys.stderr, str


##############################################
#                EXCEPTIONS                  #
##############################################

class TemplateError(Exception):
    """ Fatal exception. Raised on runtime or template syntax errors.

        This exception is raised when a runtime error occurs or when a syntax
        error in the template is found. It has one parameter which always
        is a string containing a description of the error.

        All potential IOError exceptions are handled by the module and are
        converted to TemplateError exceptions. That means you should catch the
        TemplateError exception if there is a possibility that for example
        the template file will not be accesssible.

        The exception can be raised by constructors or by any method of any
        class.
        
        The instance is no longer usable when this exception is raised. 
    """

    def __init__(self, error):
        """ Constructor.
            @hidden
        """
        Exception.__init__(self, "Htmltmpl error: " + error)


class PrecompiledError(Exception):
    """ This exception is _PRIVATE_ and non fatal.
        @hidden
    """

    def __init__(self, template):
        """ Constructor.
            @hidden
        """
        Exception.__init__(self, template)

