<?
/*  htmltmpl
    A templating engine for separation of code and HTML.

    The documentation of this templating engine is separated to two parts:
    
        1. Description of the templating language.
           
        2. Documentation of classes and API of this module that provides
           a Python/PHP implementation of the templating language.
    
    All the documentation can be found in 'doc' directory of the
    distribution tarball or at the homepage of the engine.
    Latest versions of this module are also available at that website.

    You can use and redistribute this module under conditions of the
    GNU General Public License that can be found either at
    http://www.gnu.org/ or in file "LICENSE" contained in the
    distribution tarball of this module.

    Copyright (c) 2002 Tomas Styblo, tripie@cpan.org

    WEBSITE:        http://htmltmpl.sourceforge.net/
    LICENSE:        GNU GPL
    LICENSE-URL:    http://www.gnu.org/licenses/gpl.html
    CVS:            $Id$
*/

define('_VERSION', 1.20);
define('_AUTHOR', 'Tomas Styblo <tripie@cpan.org>');

# All included templates must be placed in a subdirectory of
# a directory in which the main template is placed. The name of the
# subdirectory is defined here.
define('_INCLUDE_DIR', 'inc');

# Total number of possible parameters.
# Increment if adding a parameter to any statement.
define('_PARAMS_NUMBER', 3);

# Relative positions of parameters in TemplateCompiler->tokenize()
define('_PARAM_NAME', 1);
define('_PARAM_ESCAPE', 2);
define('_PARAM_GLOBAL', 3);

define('_PARAM_INPUT_OPTION_VALUE', 1);
define('_PARAM_INPUT_EXTRA', 2);

define('_PARAM_GETTEXT_STRING', 1);

# structure of the bytecode
define('BIN_SEP', "\000");
define('BIN_PRETOKEN', "\001");

define('INT_TMPL_LOOP', "\002");
define('INT_TMPL_ENDLOOP', "\003");
define('INT_TMPL_IF', "\004");
define('INT_TMPL_ENDIF', "\005");
define('INT_TMPL_UNLESS', "\006");
define('INT_TMPL_ENDUNLESS', "\007");
define('INT_TMPL_ELSE', "\010");
define('INT_TMPL_BOUNDARY', "\011");
define('INT_TMPL_INCLUDE', "\012");
define('INT_TMPL_GETTEXT', "\013");

define('INT_TMPL_TEXT', "\014");
define('INT_TMPL_SELECT', "\015");
define('INT_TMPL_CHECKBOX', "\016");
define('INT_TMPL_RADIO', "\017");
define('INT_TMPL_CUSTSELECT', "\020");
define('INT_TMPL_CUSTCHECKBOX', "\021");
define('INT_TMPL_CUSTRADIO', "\022");

define('INT_TMPL_ENDCUSTSELECT', "\023");
define('INT_TMPL_ENDCUSTCHECKBOX', "\024");
define('INT_TMPL_ENDCUSTRADIO', "\025");
define('INT_TMPL_OPTION', "\026");

define('INT_TMPL_STATIC', "\027");
define('INT_TMPL_VAR', "\030");


# Platform dependent defaults.
define('_DEBUG_NEWLINE_SEP', "\n");

##############################################
#         private helper functions           #
##############################################

function _DEB($str) {
    # Append a debugging message to the debug log, if debugging is active.
    # The filename of the log is defined by a global variable $HTMLTMPL_DEBUG
    # which presence also activates the debugging.
    global $HTMLTMPL_DEBUG;
    if ($HTMLTMPL_DEBUG) {
        # The code must not be interrupted, because we must make sure to
        # release the lock on the file.
        $old_ignore_user_abort = ignore_user_abort();
        ignore_user_abort(1);
        if (! ($debug_log = fopen($HTMLTMPL_DEBUG, 'a'))) {
            __error('Cannot open debugging log.');
        }
        flock($debug_log, LOCK_EX);
        fputs($debug_log, $str._DEBUG_NEWLINE_SEP);
        flock($debug_log, LOCK_UN);
        if (! fclose($debug_log)) {
            __error('Cannot close debugging log.');
        }
        ignore_user_abort($old_ignore_user_abort);
    }
}

function _last_item(&$array) {
    # Return last item of an array.
    return $array[count($array) - 1];
}

function _set_last_item(&$array, $value) {
    # Set last item of an array to value.
    $array[count($array) - 1] = $value;
}

function __error($msg) {
    user_error("HTMLTMPL: $msg ... terminating script execution.",
               E_USER_ERROR);
    die();
}

##############################################
#          CLASS: TemplateManager            #
##############################################

class TemplateManager {
    # Class that manages compilation and precompilation of templates.
    # 
    # You should use this class whenever you work with templates
    # that are stored in a file. The class can create a compiled
    # template and transparently manage its precompilation. It also
    # keeps the precompiled templates up-to-date by modification times
    # comparisons. 
    
    var $_include;
    var $_max_include;
    var $_precompile;
    var $_comments;
    var $_gettext;
    var $_static;
    var $_watch_files;
    var $_optimize_spaces;
   
    function TemplateManager($include=TRUE, $max_include=5, $precompile=TRUE,
                             $comments=TRUE, $gettext=FALSE, $optimize_spaces=FALSE) {
        # Constructor.
        #
        # param include: Enable or disable included templates.
        # This optional parameter can be used to enable or disable
        # <em>TMPL_INCLUDE</em> inclusion of templates. Disabling of
        # inclusion can improve performance a bit. The inclusion is
        # enabled by default.
        #
        # param max_include: Maximum depth of nested inclusions.
        # This optional parameter can be used to specify maximum depth of
        # nested <em>TMPL_INCLUDE</em> inclusions. It defaults to 5.
        # This setting prevents infinite recursive inclusions.
        #    
        # param precompile: Enable or disable precompilation of templates.
        # This optional parameter can be used to enable or disable
        # creation and usage of precompiled templates.
        #
        # A precompiled template is saved to the same directory in
        # which the main template file is located. You need write
        # permissions to that directory.
        #
        # Precompilation provides a significant performance boost because
        # it's not necessary to parse the templates over and over again.
        # The boost is especially noticeable when templates that include
        # other templates are used.
        #    
        # Comparison of modification times of the main template and all
        # included templates is used to ensure that the precompiled
        # templates are up-to-date. Templates are also recompiled if the
        # htmltmpl module is updated.
        #
        # An error is raised when the precompiled
        # template cannot be saved. Precompilation is enabled by default.
        #
        # Precompilation is available only on UNIX and Windows platforms,
        # because proper file locking which is necessary to ensure
        # multitask safe behaviour is platform specific and is not
        # implemented for other platforms. Attempts to enable precompilation
        # on the other platforms result in raise of an error.
        #    
        # param comments: Enable or disable template comments.
        # This optional parameter can be used to enable or disable
        # template comments.
        # Disabling of the comments can improve performance a bit.
        # Comments are enabled by default.
 
        # Save the optional parameters.
        # These values are not modified by any method. 
        $this->_include = $include;
        $this->_max_include = $max_include;
        $this->_precompile = $precompile;
        $this->_comments = $comments;
        $this->_gettext = $gettext;
        $this->_optimize_spaces = $optimize_spaces;
        $this->_static = array();
        $this->_watch_files = array();
        _DEB('INIT DONE');
    }
    
    function &prepare($file, $force_precompiled=FALSE) {
        # Factory mehod: Preprocess, parse, tokenize and compile the template.
        #    
        # If precompilation is enabled then this method tries to load
        # a precompiled form of the template from the same directory
        # in which the template source file is located. If it succeeds,
        # then it compares modification times stored in the precompiled
        # form to modification times of source files of the template,
        # including source files of all templates included via the
        # <em>TMPL_INCLUDE</em> statements. If any of the modification times
        # differs, then the template is recompiled and the precompiled
        # form updated.
        #    
        # If precompilation is disabled, then this method parses and
        # compiles the template.
        #    
        # returns: Compiled template.
        # The methods returns an instance of the <em>Template</em> class
        # which is a compiled form of the template. This instance can be
        # used as input for the <em>TemplateProcessor</em>.
        # The method returns a REFERENCE to the instance. You must use the
        # reference assignment when calling the method (=&) !
        #   
        # param file: Path to the template file to prepare.
        # The method looks for the template file in current directory
        # if the parameter is a relative path. All included templates must
        # be placed in subdirectory <strong>'inc'</strong> of the 
        # directory in which the main template file is located.
        #
        # param force_precompiled: Only use precompiled templates.
        # This parameter is useful when all your templates are precompiled
        # and located in a read-only directory. If the compiled
        # template cannot be found, an exception is raised. The engine 
        # does not check whether the compiled template is uptodate. 
        # By default this is disabled.
       
        $compiled = NULL;
        if ($this->_precompile) {
            if ($this->is_precompiled($file)) {
                $precompiled =& $this->load_precompiled($file);
                if (! $precompiled) {
                    if ($force_precompiled) {
                        __error("Force precompiled active, but cannot load precompiled templates");
                    }
                    else {
                        _DEB('PRECOMPILED: RECOMPILATION');
                        $compiled =& $this->compile($file);
                        $this->save_precompiled($compiled);
                    }
                }
                else {
                    if ($force_precompiled) {
                        _DEB('PRECOMPILED: FORCING PRECOMPILED');
                        $compiled =& $precompiled;
                    }
                    else {
                        $compile_params = array($this->_include,
                                                $this->_max_include,
                                                $this->_comments,
                                                $this->_gettext,
                                                $this->_watch_files);
                        if ($precompiled->is_uptodate($compile_params)) {
                            _DEB('PRECOMPILED: UPTODATE');
                            $compiled =& $precompiled;
                        }
                        else {
                            _DEB('PRECOMPILED: NOT UPTODATE');
                            $compiled =& $this->compile($file);
                            $this->save_precompiled($compiled); 
                        }
                    }
                }                
            }
            else {
                if ($force_precompiled) {
                    __error("Force precompiled active, but cannot load precompiled templates");
                }
                else {
                    _DEB('PRECOMPILED: NOT PRECOMPILED');
                    $compiled =& $this->compile($file);
                    $this->save_precompiled($compiled);
                }
            }
        }
        else {
            if ($force_precompiled) {
                __error("Force precompiled active, but precompilation is disabled");
            }
            else {
                _DEB('PRECOMPILATION DISABLED');
                $compiled =& $this->compile($file);
            }
        }

        return $compiled;
    }
    
    function &update(&$template) {
        # Update (recompile) a compiled template.
        #
        # This method recompiles a template compiled from a file.
        # If precompilation is enabled then the precompiled form saved on
        # disk is also updated.
        #    
        # returns: Recompiled template.
        # It's ensured that the returned template is up-to-date.
        # The method returns a REFERENCE to the template. You must use the
        # reference assignment when calling the method (=&) !        
        #    
        # param template: A compiled template.
        # This parameter should be an instance of the <em>Template</em>
        # class, created either by the <em>TemplateManager</em> or by the
        # <em>TemplateCompiler</em>. The instance must represent a template
        # compiled from a file on disk.
        _DEB('UPDATE');
        $updated =& $this->compile($template->file());
        if ($this->_precompile) {
            $this->save_precompiled($updated);
        }
        return $updated;
    }
  
    function static_data($static) {
        if (is_array($static)) {
            $this->_static =& $static;
        }
        else {
            __error("Parameter to static_data() must be associative array.");
        }
    }
  
    function watch_files($files) {
        if (is_array($files)) {
            $this->_watch_files =& $files;
        }
        else {
            __error("Parameter to watch_files() must be associative array.");
        }
    }
  
    ##############################################
    #              PRIVATE METHODS               #
    ##############################################  
    
    function &compile($file) {
        # Compile the template from a file.
        # The method returns a REFERENCE to the template. You must use the
        # reference assignment when calling the method (=&) !        
        $tmplc = new TemplateCompiler($this->_include, $this->_max_include,
                                      $this->_comments, $this->_gettext,
                                      $this->_optimize_spaces);
        $tmplc->static_data($this->_static);
        $tmplc->watch_files($this->_watch_files);
        return $tmplc->compile($file);
    }
    
    function is_precompiled($file) {
        # Return true if the template is already precompiled on the disk.
        # This method doesn't check whether the compiled template is uptodate.
        $filename = $file.'c';     # "template.tmplc"
        if (file_exists($filename) && is_readable($filename)) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }
    
    function &load_precompiled($file) {
        # Load precompiled template from disk.
        # Remove the precompiled template file and recompile the template
        # if the file contains corrupted data.
        # The method returns a REFERENCE to the template. You must use the
        # reference assignment when calling the method (=&) !          
        
        $filename = $file.'c';     # "template.tmplc"
        _DEB('LOADING PRECOMPILED');
        
        # Prevent script interruption while we hold a file lock.
        $old_ignore_user_abort = ignore_user_abort();
        ignore_user_abort(1);
        
        # Open the file.
        if (! ($precompiled_file = fopen($filename, 'rb'))) {
            __error("Cannot open precompiled template '$filename'.");
        }
        
        # Lock, read and unlock it.
        flock($precompiled_file, LOCK_SH);
        $precompiled_data = fread($precompiled_file, filesize($filename));
        flock($precompiled_file, LOCK_UN);
        
        # Close it.
        if (! fclose($precompiled_file)) {
            __error("Cannot close precompiled template '$filename'.");
        }
        ignore_user_abort($old_ignore_user_abort);
        $precompiled = unserialize($precompiled_data);
        
        # Check whether the precompiled data is not corrupted.
        if (is_object($precompiled)) {
            _DEB('LOADING: OK');        
            return $precompiled;
        }
        else {
            _DEB('LOADING: NOT OK');            
            if (is_file($filename) && unlink($filename) == FALSE) {
                __error("Cannot delete invalid precompiled template ".
                           "'$filename'.");
            }
            return NULL;
        }
    }
    
    function save_precompiled(&$template) {
        # Save compiled template to disk in precompiled form.
        # Associated metadata is also saved. It includes: filename of the
        # main template file, modification time of the main template file,
        # modification times of all included templates and version of the
        # htmltmpl module which compiled the template.
        
        $filename = $template->file().'c';   # "template.tmplc"
        $template_dir = dirname(realpath($template->file()));
        
        # Check if we have write permission to the template's directory.
        if (! is_writable($template_dir)) {
            __error("Cannot save precompiled templates to '$template_dir':".
                       "write permission denied.");
        }
        
        # Prevent script interruption while we hold a file lock.
        $old_ignore_user_abort = ignore_user_abort();
        ignore_user_abort(1);

        # Open the file.
        if (! ($precompiled_file = fopen($filename, 'wb'))) {
            __error("Cannot save precompiled template '$filename'.");
        }
        
        # Lock, read and unlock it.
        flock($precompiled_file, LOCK_EX);
        fwrite($precompiled_file, serialize($template));
        flock($precompiled_file, LOCK_UN);
        
        # Close it.
        if (! fclose($precompiled_file)) {
            __error("Cannot close precompiled template '$filename'.");
        }
        ignore_user_abort($old_ignore_user_abort);
        _DEB('SAVING PRECOMPILED');

        $this->save_precompiled_cproc($template);
    }

    function save_precompiled_cproc(&$template) {
        $filename = $template->file().'cc';   # "template.tmplcc"
        $template_dir = dirname(realpath($template->file()));
        
        # Check if we have write permission to the template's directory.
        if (! is_writable($template_dir)) {
            __error("Cannot save precompiled templates to '$template_dir':".
                       "write permission denied.");
        }
        
        # Prevent script interruption while we hold a file lock.
        $old_ignore_user_abort = ignore_user_abort();
        ignore_user_abort(1);

        # Open the file.
        if (! ($precompiled_file = fopen($filename, 'wb'))) {
            __error("Cannot save precompiled template '$filename'.");
        }
        
        # create the cproc bytecode
        $data = implode(BIN_SEP, $template->_tokens);
        
        # Lock, read and unlock it.
        flock($precompiled_file, LOCK_EX);
        fwrite($precompiled_file, $data);
        flock($precompiled_file, LOCK_UN);
        
        # Close it.
        if (! fclose($precompiled_file)) {
            __error("Cannot close precompiled template '$filename'.");
        }
        ignore_user_abort($old_ignore_user_abort);
        _DEB('SAVING CPROC PRECOMPILED');
    }
}


##############################################
#          CLASS: TemplateProcessor          #
##############################################

class TemplateProcessor {
    # Fill the template with data and process it.
    # This class provides actual processing of a compiled template.
    # Use it to set template variables and loops and then obtain
    # result of the processing.

    var $_html_escape;
    var $_magic_vars;
    var $_global_vars;
    var $_vars;
    var $_current_part;
    var $_current_pos;
    var $_gettext_func;
 
    function TemplateProcessor($html_escape=TRUE, $magic_vars=TRUE,
                               $global_vars=FALSE, $gettext_func=NULL) {
        # Constructor.

        # param html_escape: Enable or disable HTML escaping of variables.
        # This optional parameter is a flag that can be used to enable or
        # disable automatic HTML escaping of variables.
        # All variables are by default automatically HTML escaped. 
        # The escaping process substitutes HTML brackets, ampersands and
        # double quotes with appropriate HTML entities.
        #    
        # param magic_vars: Enable or disable loop magic variables.
        # This parameter can be used to enable or disable
        # "magic" context variables, that are automatically defined inside
        # loops. Magic variables are enabled by default.
        #
        # Refer to the language specification for description of these
        # magic variables.
        #     
        # param global_vars: Globally activate global lookup of variables.
        # This optional parameter is a flag that can be used to specify
        # whether variables which cannot be found in the current scope
        # should be automatically looked up in enclosing scopes.
        #
        # Automatic global lookup is disabled by default. Global lookup
        # can be overriden on a per-variable basis by the
        # <strong>GLOBAL</strong> parameter of a <strong>TMPL_VAR</strong>
        # statement.
                       
        $this->_html_escape = $html_escape;
        $this->_magic_vars = $magic_vars;
        $this->_global_vars = $global_vars;

        # Data structure containing variables and loops set by the
        # application. It's a hierarchical associative array of mappings.
        # It's modified only by the set() and reset() methods.
        $this->_vars = array();

        # Following variables are for multipart templates.
        $this->_current_part = 1;
        $this->_current_pos = 0;
        
        # Gettext resolve function
        $this->_gettext_func = $gettext_func;
    }
    
    function set($var, $value=NULL) {
        # Associate a value with top-level template variable or loop.
        #
        # A template identifier can represent either an ordinary variable
        # (string) or a loop.
        #
        # To assign a value to a string identifier pass a scalar as the 'value'
        # parameter. This scalar will be automatically converted to string.
        #
        # To assign a value to a loop identifier pass a list of mappings as the
        # 'value' parameter. The engine iterates over this list and assigns
        # values from the mappings to variables in a template loop block if a
        # key in the mapping corresponds to a name of a variable in the loop
        # block. The number of mappings contained in this list is equal to
        # number of times the loop block is repeated in the output.
        #
        # The method can be called with either one or two parameters.  When
        # it's called with two parameters, its behaviour is exactly as
        # described above.
        #
        # When it's called with one parameter only, then the parameter must be
        # an associative array.  The function loops over this array, uses keys
        # of the array as names of variables and values of the array as values
        # of the variables. This can be used for fast multiassignemnts.  The
        # values itself may be associative arrays, in which case the assignment
        # is a loop assignment.
        #
        # returns: No return value.  param var: Name of template variable or
        # loop.  param value: The value to associate.

        if (is_array($var) && $value == NULL) {
            foreach ($var as $mkey => $mvalue) {
                $this->_vars[$mkey] =& $mvalue;
                _DEB("VALUE SET: $mkey");
            }
        }
        else {
            $this->_vars[$var] =& $value;
            _DEB("VALUE SET: $var");
        }
    }

    function loop() {
        $args = func_get_args();
        if (count($args) < 2) {
            __error("loop: init: not enough parameters (name, varnames ...)");
            return NULL;
        }
        $name = array_shift($args);
        $loop = new TemplateLoop($this, $name, $args);
        return $loop;
    }

    function reset($keep_data=FALSE) {
        # Reset the template data.
        #
        # This method resets the data contained in the template processor
        # instance. The template processor instance can be used to process
        # any number of templates, but this method must be called after
        # a template is processed to reuse the instance,
        #
        # returns: No return value.
        # param keep_data: Do not reset the template data.
        # Use this flag if you do not want the template data to be erased.
        # This way you can reuse the data contained in the instance of
        # the <em>TemplateProcessor</em>.
        
        $this->_current_part = 1;
        $this->_current_pos = 0;
        if (! $keep_data) {
            $this->_vars = array();
        }
        _DEB('RESET');
    }  
    
    function process(&$template, $part=NULL) {
        # Process a compiled template. Return the result as string.
        #
        # This method actually processes a template and returns
        # the result.
        #
        # returns: Result of the processing as string.
        #
        # param template: A compiled template.
        # Value of this parameter must be an instance of the
        # <em>Template</em> class created either by the
        # <em>TemplateManager</em> or by the <em>TemplateCompiler</em>.
        #
        # param part: The part of a multipart template to process.
        # This parameter can be used only together with a multipart
        # template. It specifies the number of the part to process.
        # It must be greater than zero, because the parts are numbered
        # from one.
        #
        # The parts must be processed in the right order. You
        # cannot process a part which precedes an already processed part.
        #
        # If this parameter is not specified, then the whole template.
        # is processed, or all remaining parts are processed.   
        
        _DEB("PROCESSING");
        # print_r($tokens);     # debugging - enable to see app input

        if ($part != NULL && ($part == 0 || $part < $this->_current_part)) {
            __error('htmltmpl - process(): invalid part number.');
        }    
        
        # This flag means "jump behind the end of current statement" or
        # "skip the parameters of current statement".
        # Even parameters that actually are not present in the template
        # do appear in the list of tokens as empty items !   
        $skip_params = FALSE;
        
        # Stack for enabling or disabling output in response to TMPL_IF,
        # TMPL_UNLESS, TMPL_ELSE and TMPL_LOOPs with no passes.
        $output_control = array();
        $ENABLE_OUTPUT = 1;
        $DISABLE_OUTPUT = 0;
        
        # Stacks for data related to loops.
        $loop_name = array();   # name of a loop
        $loop_pass = array();   # current pass of a loop (counted from zero)
        $loop_start = array();  # index of loop start in token list
        $loop_total = array();  # total number of passes in a loop
        $cust_type = array();  # used only by <TMPL_CUST*> tags
        $cust_name = array();  # used only by <TMPL_CUST*> tags

        $tokens =& $template->tokens();
        $len_tokens = count($tokens);
        $out = '';              # buffer for processed output
        
        # Recover position at which we ended after processing of last part.
        $i = $this->_current_pos;
        
        # Process the list of tokens.        
        while (TRUE) {
            if ($i == $len_tokens) {
                break;
            }
            if ($skip_params) {
                # Skip the parameters following a statement.
                $skip_params = FALSE;
                $i += _PARAMS_NUMBER;
                continue;
            }
            
            $token = $tokens[$i];
            if ($token{0} == BIN_PRETOKEN) {
                $token = $token{1};

                if ($token == INT_TMPL_VAR) {
                    # TMPL_VARs should be first. They are the most common.
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_VAR>.');
                    }
                    $escape = $tokens[$i + _PARAM_ESCAPE];
                    $global = $tokens[$i + _PARAM_GLOBAL];
                    $skip_params = TRUE;
                    
                    # If output of current block is not disabled then append
                    # the substitued and escaped variable to the output.
                    if (! in_array($DISABLE_OUTPUT, $output_control)) {
                        $value = $this->find_value($var, $loop_name,
                                                   $loop_pass, $loop_total,
                                                   $global);
                        $out .= $this->escape($this->_html_escape, $value, $escape);
                        _DEB("VAR: $var");
                    }
                }

                elseif ($token == INT_TMPL_LOOP) {
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_LOOP>.');
                    }
                    $skip_params = TRUE;

                    # Find total number of passes in this loop.
                    $passtotal = $this->find_value($var, $loop_name,
                                                   $loop_pass, $loop_total);
                    if (! $passtotal) {
                        $passtotal = 0;
                    }
                    # Push data for this loop on the stack.
                    array_push($loop_total, $passtotal);
                    array_push($loop_start, $i);
                    array_push($loop_pass, 0);
                    array_push($loop_name, $var);

                    # Disable output of loop block if the number of passes
                    # in this loop is zero.
                    if ($passtotal == 0) {
                        # This loop is empty.
                        array_push($output_control, $DISABLE_OUTPUT);
                        _DEB("LOOP: DISABLE: $var");
                    }
                    else {
                        array_push($output_control, $ENABLE_OUTPUT);
                        _DEB("LOOP: FIRST PASS: $var TOTAL: $passtotal");
                    }
                }

                elseif ($token == INT_TMPL_IF) {
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_IF>.');
                    }
                    $global = $tokens[$i + _PARAM_GLOBAL];
                    $skip_params = TRUE;
                    if ($this->find_value($var, $loop_name, $loop_pass,
                                          $loop_total, $global)) {
                        array_push($output_control, $ENABLE_OUTPUT);
                        _DEB("IF: ENABLE: $var");
                    }
                    else {
                        array_push($output_control, $DISABLE_OUTPUT);
                        _DEB("IF: DISABLE: $var");
                    }
                }

                elseif ($token == INT_TMPL_UNLESS) {
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_UNLESS>.');
                    }
                    $global = $tokens[$i + _PARAM_GLOBAL];
                    $skip_params = TRUE;
                    if ($this->find_value($var, $loop_name, $loop_pass,
                                          $loop_total, $global)) {
                        array_push($output_control, $DISABLE_OUTPUT);
                        _DEB("UNLESS: DISABLE: $var");
                    }
                    else {
                        array_push($output_control, $ENABLE_OUTPUT);
                        _DEB("UNLESS: ENABLE: $var");
                    }
                }

                elseif ($token == INT_TMPL_ENDLOOP) {
                    $skip_params = TRUE;
                    if (! $loop_name) {
                        __error('Unmatched </TMPL_LOOP>.');
                    }
                    
                    # If this loop was not disabled, then record the pass.
                    if (_last_item($loop_total) > 0) {
                        $loop_pass[count($loop_pass) - 1]++;
                    }
                    
                    if (_last_item($loop_pass) == _last_item($loop_total)) {
                        # There are no more passes in this loop. Pop
                        # the loop from stack.
                        array_pop($loop_pass);
                        array_pop($loop_name);
                        array_pop($loop_start);
                        array_pop($loop_total);
                        array_pop($output_control);
                        _DEB('LOOP: END');
                    }
                    else {
                        # Jump to the beggining of this loop block 
                        # to process next pass of the loop.
                        $i = _last_item($loop_start);
                        _DEB('LOOP: NEXT PASS');
                    }
                }

                elseif ($token == INT_TMPL_ENDIF) {
                    $skip_params = TRUE;
                    if (! $output_control) {
                        __error('Unmatched </TMPL_IF>.');
                    }
                    array_pop($output_control);
                    _DEB('IF: END');
                }    

                elseif ($token == INT_TMPL_ENDUNLESS) {
                    $skip_params = TRUE;
                    if (! $output_control) {
                        __error('Unmatched </TMPL_UNLESS>.');
                    }
                    array_pop($output_control);
                    _DEB('UNLESS: END');
                }

                elseif ($token == INT_TMPL_ELSE) {
                    $skip_params = TRUE;
                    if (! $output_control) {
                        __error('Unmatched <TMPL_ELSE>.');
                    }
                    if (_last_item($output_control) == $DISABLE_OUTPUT) {
                        # Condition was false, activate the ELSE block.
                        _set_last_item($output_control, $ENABLE_OUTPUT);
                        _DEB('ELSE: ENABLE');
                    }
                    elseif (_last_item($output_control) == $ENABLE_OUTPUT) {
                        # Condition was true, deactivate the ELSE block.
                        _set_last_item($output_control, $DISABLE_OUTPUT);
                        _DEB('ELSE: DISABLE');
                    }
                    else {
                        __error('BUG: ELSE: INVALID FLAG.');
                    }                
                }

                elseif ($token == INT_TMPL_BOUNDARY) {
                    if ($part && $part == $this->_current_part) {
                        _DEB('BOUNDARY ON');
                        $this->_current_part++;
                        $this->_current_pos = $i + 1 + _PARAMS_NUMBER;
                        break;
                    }
                    else {
                        $skip_params = TRUE;
                        _DEB('BOUNDARY OFF');
                        $this->_current_part++;
                    }
                }

                elseif ($token == INT_TMPL_INCLUDE) {
                    # TMPL_INCLUDE is left in the compiled template only
                    # when it was not replaced by the parser.
                    $skip_params = TRUE;
                    $filename = $tokens[$i + _PARAM_NAME];
                    $out .= "
                        <br />
                        <p>
                        <strong>HTMLTMPL WARNING:</strong><br />
                        Cannot include template: <strong>$filename</strong>
                        </p>
                        <br />
                    ";
                    _DEB('CANNOT INCLUDE WARNING');
                }                

                elseif ($token == INT_TMPL_GETTEXT) {
                    $skip_params = TRUE;
                    if (! in_array($DISABLE_OUTPUT, $output_control)) {
                        $text = $tokens[$i + _PARAM_GETTEXT_STRING];
                        if ($this->_gettext_func == NULL) {
                            $out .= gettext($text);
                        }
                        else {
                            $func = $this->_gettext_func;
                            $out .= $func($text);
                        }
                        _DEB("GETTEXT: $text");
                    }
                }
 
                elseif ($token == INT_TMPL_TEXT) {
                    $name = $tokens[$i + _PARAM_NAME];
                    $extra = $tokens[$i + _PARAM_INPUT_EXTRA];
                    if (! $name) {
                        __error('No identifier in <TMPL_TEXT>.');
                    }
                    $skip_params = TRUE;

                    _DEB("TMPL_TEXT: $name");
                    
                    if (! in_array($DISABLE_OUTPUT, $output_control)) {
                        $value = $this->find_value($name, $loop_name,
                               $loop_pass, $loop_total, FALSE);
                        $out .= '<input type="text" name="'.htmlspecialchars($name).'" '.
                                'value="'.htmlspecialchars($value)."\" $extra />"."\n";
                    }
                }
                
                elseif ($token == INT_TMPL_SELECT || 
                        $token == INT_TMPL_CHECKBOX ||
                        $token == INT_TMPL_RADIO) {
                    $name = $tokens[$i + _PARAM_NAME];
                    $extra = $tokens[$i + _PARAM_INPUT_EXTRA];
                    if (! $name) {
                        __error('No identifier in <TMPL_SELECT/CHECKBOX/RADIO>.');
                    }
                    $skip_params = TRUE;

                    _DEB("SELECT or CHECKBOX or RADIO: $name");
                    
                    if (! in_array($DISABLE_OUTPUT, $output_control)) {
                        # Find total number of passes in this loop.
                        $passtotal = $this->find_value($name, $loop_name,
                                                       $loop_pass, $loop_total);
                        if ($passtotal > 0) {
                            if ($token == INT_TMPL_SELECT) {
                                $out .= '<select name="'.htmlspecialchars($name)."\" $extra>\n";
                            }
                            else if ($token == INT_TMPL_CHECKBOX) {
                                $out .= "<!-- checkbox: ".htmlspecialchars($name)." -->\n";
                            }
                            else if ($token == INT_TMPL_RADIO) {
                                $out .= "<!-- radio: ".htmlspecialchars($name)." -->\n";
                            }
                            
                            # Push data for this loop on the stack.
                            array_push($loop_total, $passtotal);
                            array_push($loop_start, $i);
                            array_push($loop_pass, 0);
                            array_push($loop_name, $name);

                            while (TRUE) {
                                $value = $this->find_value("value", $loop_name,
                                                           $loop_pass, $loop_total,
                                                           FALSE);
                                $text = $this->find_value("text", $loop_name,
                                                          $loop_pass, $loop_total,
                                                          FALSE);

                                # accept both "checked" and "selected"
                                $selected = $this->find_value("selected", $loop_name,
                                                          $loop_pass, $loop_total,
                                                          FALSE);

                                $checked = $this->find_value("checked", $loop_name,
                                                          $loop_pass, $loop_total,
                                                          FALSE);

                                if ($selected || $checked) {
                                    $sel = TRUE;
                                }
                                else {
                                    $sel = FALSE;
                                }

                                if ($token == INT_TMPL_SELECT) {
                                    if ($sel) { $sel = "selected"; }
                                    $out .= '<option value="'.htmlspecialchars($value)."\" $sel>".
                                            htmlspecialchars($text)."</option>\n";
                                }
                                else if ($token == INT_TMPL_CHECKBOX) {
                                     if ($sel) { $sel = "checked"; }
                                     $out .= '<input type="checkbox" name="'.htmlspecialchars($name).'" '.
                                             'value="'.htmlspecialchars($value)."\" $sel $extra />".' '.
                                            htmlspecialchars($text)."\n";
                                }
                                else if ($token == INT_TMPL_RADIO) {
                                      if ($sel) { $sel = "checked"; }
                                      $out .= '<input type="radio" name="'.htmlspecialchars($name).'" '.
                                             'value="'.htmlspecialchars($value)."\" $sel $extra />".' '.
                                            htmlspecialchars($text)."\n";
                                }

                                # If this loop was not disabled, then record the pass.
                                if (_last_item($loop_total) > 0) {
                                    $loop_pass[count($loop_pass) - 1]++;
                                }
                               
                                if (_last_item($loop_pass) == _last_item($loop_total)) {
                                    # There are no more passes in this loop. Pop
                                    # the loop from stack.
                                    array_pop($loop_pass);
                                    array_pop($loop_name);
                                    array_pop($loop_start);
                                    array_pop($loop_total);
                                    _DEB('SELECT/CHECKBOX/RADIO: END');
                                    break;
                                }
                            }                     
                            $out .= "</select>\n";
                        }
                    }
                }
 
                elseif ($token == INT_TMPL_CUSTSELECT || 
                        $token == INT_TMPL_CUSTCHECKBOX ||
                        $token == INT_TMPL_CUSTRADIO) {
                    $name = $tokens[$i + _PARAM_NAME];
                    $extra = $tokens[$i + _PARAM_INPUT_EXTRA];
                    if (! $name) {
                        __error('No identifier in <TMPL_CUSTSELECT/CUSTCHECKBOX/CUSTRADIO>.');
                    }
                    $skip_params = TRUE;

                    if (! in_array($DISABLE_OUTPUT, $output_control)) {
                        if ($token == INT_TMPL_CUSTSELECT) {
                            $out .= '<select name="'.htmlspecialchars($name)."\" $extra>\n";
                        }
                        else if ($token == INT_TMPL_CUSTCHECKBOX) {
                             $out .= '<!-- custcheckbox: '.htmlspecialchars($name)." -->\n";
                        }
                        else if ($token == INT_TMPL_CUSTRADIO) {
                             $out .= '<!-- custradio: '.htmlspecialchars($name)." -->\n";
                        }
                    }

                    _DEB("CUSTSELECT or CUSTCHECKBOX or CUSTRADIO: $name");

                    array_push($cust_type, $token);
                    array_push($cust_name, $name);
                }
                
                elseif ($token == INT_TMPL_OPTION) {
                    $value = $tokens[$i + _PARAM_INPUT_OPTION_VALUE];
                    $extra = $tokens[$i + _PARAM_INPUT_EXTRA];
                    if (strlen($value) == 0) {
                        __error('No value in <TMPL_CUSTSELECT/CUSTCHECKBOX/CUSTRADIO>.');
                    }
                    $skip_params = TRUE;
                    
                    if (! in_array($DISABLE_OUTPUT, $output_control)) {
                        $sel = '';
                        $found_value = $this->find_value(_last_item($cust_name), $loop_name,
                                                         $loop_pass, $loop_total,
                                                         $global);
                        $found_values = explode(",", $found_value);
                        foreach ($found_values as $fv) {
                            if ($value == $fv) {
                                $sel = TRUE;
                            }
                        }
                        
                        $ctype = _last_item($cust_type);
                        switch ($ctype) {
                            case INT_TMPL_CUSTSELECT:
                                if ($sel) { $sel = "selected"; }
                                $out .= '<option value="'.htmlspecialchars($value)."\" $sel $extra>";
                                break;
                            case INT_TMPL_CUSTCHECKBOX:
                                if ($sel) { $sel = "checked"; }
                                $out .= '<input type="checkbox" name="'.htmlspecialchars($name).'" '.
                                        'value="'.htmlspecialchars($value)."\" $sel $extra />";
                                break;
                            case INT_TMPL_CUSTRADIO:
                                if ($sel) { $sel = "checked"; }
                                $out .= '<input type="radio" name="'.htmlspecialchars($name).'" '.
                                        'value="'.htmlspecialchars($value)."\" $sel $extra />";
                                break;
                        }
                        _DEB("CUST OPTION: $value");
                    }
                }
                
                elseif ($token == INT_TMPL_ENDCUSTSELECT || 
                        $token == INT_TMPL_ENDCUSTCHECKBOX ||
                        $token == INT_TMPL_ENDCUSTRADIO) {
                    $skip_params = TRUE;
                    if (! $cust_name) {
                        __error('Unmatched </TMPL_CUST>.');
                    }

                    if (_last_item($cust_type) == INT_TMPL_CUSTSELECT) {
                        if (! in_array($DISABLE_OUTPUT, $output_control)) {
                            $out .= "</select>\n";
                        }
                    }
                    
                    array_pop($cust_type);
                    array_pop($cust_name);
                    _DEB('CUST: END');
                }

                else {
                    # Unknown processing directive.
                    __error("Invalid statement $token>.");
                }                                  
            }
            elseif (! in_array($DISABLE_OUTPUT, $output_control)) {
                # Raw textual template data.
                # If output of current block is not disabled, then 
                # append the template data to the output buffer.
                $out .= $token;
            }
                
            $i++;            
        }   # end of the big while loop

        # Check whether all opening statements were closed.
        if ($loop_name) {
            __error('Missing </TMPL_LOOP>.');
        }
        if ($output_control) {
            __error('Missing </TMPL_IF> or </TMPL_UNLESS>.');
        }

        return $out;
    }

    ##############################################
    #              PRIVATE METHODS               #
    ##############################################
    
    function find_value($var, &$loop_name, &$loop_pass, &$loop_total,
                        $global_override=NULL) {
        # Search the $this->_vars data structure to find variable var
        # located in currently processed pass of a loop which
        # is currently being processed. If the variable is an ordinary
        # variable, then return it.
        #    
        # If the variable is an identificator of a loop, then 
        # return the total number of times this loop will
        # be executed.
        #    
        # Return an empty string, if the variable is not
        # found at all.                       
                        
        # Search for the requested variable in magic vars if the name
        # of the variable starts with "__" and if we are inside a loop.
        if ($this->_magic_vars && substr($var, 0, 2) == '__' && $loop_name) {
            return $this->magic_var($var, _last_item($loop_pass),
                                    _last_item($loop_total));
        }
        
        # Search for an ordinary variable or for a loop.
        # Recursively search in self._vars for the requested variable.
        $scope =& $this->_vars;
        $globals = array();
        for ($i = 0; $i < count($loop_name); $i++) {            
            # If global lookup is on then push the value on the stack.
            if ((($this->_global_vars && $global_override != '0') ||
                  $global_override == '1') && $scope[$var] &&
                  !is_array($scope[$var])) {
                array_push($globals, $scope[$var]);
            }
            
            # Descent deeper into the hierarchy.
            if ($scope[$loop_name[$i]]) {
                $scope =& $scope[$loop_name[$i]][$loop_pass[$i]];
            }
            else {
                return '';
            }
        }
            
        if (isset($scope[$var])) {
            # Value exists in current loop.
            if (is_array($scope[$var])) {
                # The requested value is a loop.
                # Return total number of its passes.
                return count($scope[$var]);
            }
            else {
                return $scope[$var];
            }
        }
        elseif ($globals &&
                (($this->_global_vars && $global_override != '0') ||
                  $global_override == '1')) {
            # Return globally looked up value.
            return array_pop($globals);
        }
        else {
            # No value found.
            /*
            # !!! this no longer works because we do not enforce first letter
            # uppercase in loops
            if ($var{0} == strtoupper($var{0})) {
                # This is a loop name.
                # Return zero, because the user wants to know number
                # of its passes.
                return 0;
            }
            else {
                return '';
            }
            */
            return '';
        }
    }
    
    function magic_var($var, $loop_pass, $loop_total) {
        # Resolve and return value of a magic variable.
        # Raise an error if the magic variable is not recognized.
        
        _DEB("MAGIC: '$var', PASS: $loop_pass, TOTAL: $loop_total");
        if ($var == '__FIRST__') {
            if ($loop_pass == 0) {
                return 1;
            }
            else {
                return 0;
            }
        }
        elseif ($var == '__LAST__') {
            if ($loop_pass == $loop_total - 1) {
                return 1;
            }
            else {
                return 0;
            }
        }
        elseif ($var == '__INNER__') {
            # If this is neither the first nor the last pass.
            if ($loop_pass != 0 && $loop_pass != $loop_total - 1) {
                return 1;
            }
            else {
                return 0;
            }
        }
        elseif ($var == '__PASS__') {
            # Magic variable __PASS__ counts passes from one.
            return $loop_pass + 1;
        }
        elseif ($var == '__PASSTOTAL__') {
            return $loop_total;
        }
        elseif ($var == '__ODD__') {
            # Internally pass numbers stored in loop_pass are counted from
            # zero. But the template language presents them counted from one.
            # Therefore we must add one to the actual loop_pass value to get
            # the value we present to the user.
            if (($loop_pass + 1) % 2 != 0) {
                return 1;
            }
            else {
                return 0;
            }
        }
        elseif (substr($var, 0, 9) == '__EVERY__') {
            # Magic variable __EVERY__x is never true in first or last pass.
            if ($loop_pass != 0 && $loop_pass != $loop_total - 1) {
                # Check if an integer follows the variable name.
                $every = intval(substr($var, 9));  # 9 == length of __EVERY__
                if (! $every) {
                    __error('Magic variable __EVERY__x: pass number '.
                               'cannot be zero.');
                }
                else {
                    if (($loop_pass + 1) % $every == 0) {
                        _DEB("MAGIC: EVERY: $every");
                        return 1;
                    }
                    else {
                        return 0;
                    }
                }
            }
            else {
                return 0;
            }
        }
        else {
            __error("Invalid magic variable '$var'.");
        }    
    }
    
    function escape($default, $str, $override=NULL) {
        # Escape a string either by HTML escaping or by URL escaping.
        if (($default && $override != 'NONE' && $override != '0' &&
             $override != 'URL' && $override != 'WAP') 
                || $override == 'HTML' || $override == '1') {
            return htmlspecialchars($str);
        }
        elseif ($override == 'URL') {
            return htmlspecialchars(urlencode($str));
        }
        elseif ($override == 'WAP') {
            return str_replace('$', '$$', htmlspecialchars($str));
        }
        else {
            return $str;
        }
    }
}


##############################################
#          CLASS: TemplateCompiler           #
##############################################

class TemplateCompiler {
    # Preprocess, parse, tokenize and compile the template.
    #
    # This class parses the template and produces a 'compiled' form
    # of it. This compiled form is an instance of the <em>Template</em>
    # class. The compiled form is used as input for the TemplateProcessor
    # which uses it to actually process the template.
    #
    # This class should be used direcly only when you need to compile
    # a template from a string. If your template is in a file, then you
    # should use the <em>TemplateManager</em> class which provides
    # a higher level interface to this class and also can save the
    # compiled template to disk in a precompiled form.
    
    var $_include;
    var $_max_include;
    var $_comments;
    var $_gettext;
    var $_include_files;
    var $_include_level;
    var $_include_path;
    var $_static;
    var $_watch_files;
    var $_optimize_spaces;
    
    function TemplateCompiler($include=TRUE, $max_include=5, $comments=TRUE,
                              $gettext=FALSE, $optimize_spaces=FALSE) {
        # Constructor.
        #
        # param include: Enable or disable included templates.
        # param max_include: Maximum depth of nested inclusions
        # param comments: Enable or disable template comments.
        
        $this->_include = $include;
        $this->_max_include = $max_include;
        $this->_comments = $comments;
        $this->_gettext = $gettext;
        $this->_optimize_spaces = $optimize_spaces;

        # This is a list of filenames of all included templates.
        # It's modified by the include_templates() method.
        $this->_include_files = array();
        
        # This is a counter of current inclusion depth. It's used to prevent
        # infinite recursive includes.
        $this->_include_level = 0;
        $this->_include_path = NULL;

        # Static data.
        $this->_static = array();
        $this->_watch_files = array();
    }
   
    function static_data($static) {
        # Define static template variables.
        #
        # First parameter is an associative array which contains
        # names of the variables (keys of the array) and their corresponding
        # values (values of the array).
        #
        # param static: Dictionary of name/value pairs. 
    
        if (is_array($static)) {
            $this->_static =& $static;
        }
        else {
            __error("Parameter to static_data() must be associative array.");
        }
    }

    function watch_files($files) {
        # Monitor specified files for changes. 
        #
        # This function can be used to monitor files for changes. 
        # If a file changes, then the template will be automatically
        # recompiled.
        #
        # This is very useful if you use static variables (TMPL_STATIC)
        # and store their values in a separate file. Please consult
        # language documentation for more info on static variables.
        #
        # param files: An array of names of files to monitor
        
        if (is_array($files)) {
            $this->_watch_files =& $files;
        }
        else {
            __error("Parameter to watch_files() must be associative array.");
        }
    }
   
    function &compile($file) {
        # Compile a template from a file.
        #
        # returns: REFERENCE to an instance of compiled template.
        # param file: Filename of the template.
        # See the <em>prepare()</em> method of the <em>TemplateManager</em>
        # class for exaplanation of this parameter.
        
        _DEB("COMPILING FROM FILE: $file");
        $this->_include_path = dirname($file).'/'._INCLUDE_DIR;
        $tokens =& $this->parse($this->read($file));
        $compile_params = array($this->_include,
                                $this->_max_include,
                                $this->_comments,
                                $this->_gettext,
                                $this->_watch_files);
        return new Template(_VERSION, $file, 
                            array_merge($this->_include_files, 
                                        $this->_watch_files),
                            $tokens, $compile_params);
    }
    
    function &compile_string($data) {
        # Compile template from a string.
        #
        # This method compiles a template from a string. The
        # template cannot include any templates.
        # <strong>TMPL_INCLUDE</strong> statements are turned into warnings.
        #
        # returns: REFERENCE to an instance of compiled template.
        # param data: String containing the template data.
                   
        _DEB('COMPILING FROM STRING');
        $this->_include = FALSE;
        $tokens =& $this->parse($data);
        $compile_params = array($this->_include,
                                $this->_max_include,
                                $this->_comments,
                                $this->_gettext,
                                $this->_watch_files);
        return new Template(_VERSION, NULL, NULL, $tokens, $compile_params);
    }
    
    ##############################################
    #              PRIVATE METHODS               #
    ##############################################
    
    function read($filename) {
        # Read content of file and return it. Raise an error if a problem
        # occurs.
        _DEB("READING: $filename");
        
        # Does the file exist ?
        if (! file_exists($filename)) {
            __error("Template '$filename' does not exist.");
        }
        
        # Read it.
        if (! ($template_file = fopen($filename, 'r'))) {
            __error("Cannot open template '$filename'.");
        }
        $data = fread($template_file, filesize($filename));
        if (! fclose($template_file)) {
            __error("Cannot close template '$filename'.");
        }
        return $data;
    }
    
    function &parse($template_data) {
        # Parse the template. This method is recursively called from
        # within the include_templates() method.
        # Returns a REFERENCE to list of processing tokens.
        if ($this->_comments) {
            _DEB('PARSER: COMMENTS');
            $template_data = $this->remove_comments($template_data);
        }

        $tokens =& $this->tokenize($template_data);

        if ($this->_include) {
            _DEB('PARSER: INCLUDES');
            $this->include_templates($tokens);
        }
        return $tokens;
    }
    
    function remove_comments($template_data) {
        # Remove comments from the template data.
        $pattern = '### .*';
        return preg_replace("/$pattern/", '', $template_data);
    }
    
    function include_templates(&$tokens) {
        # Process TMPL_INCLUDE statements. Use the include_level counter
        # to prevent infinite recursion. Record paths to all included
        # templates to $this->_include_files.
        $i = 0;
        $skip_params = FALSE;
        
        # Process the list of tokens.
        while (TRUE) {
            if ($i == count($tokens)) {
                break;
            }
            if ($skip_params) {
                $skip_params = FALSE;
                $i += _PARAMS_NUMBER;
                continue;
            }

            $token = $tokens[$i];
            if ($token == BIN_PRETOKEN.INT_TMPL_INCLUDE) {
                $filename = $tokens[$i + _PARAM_NAME];
                if (! $filename) {
                    __error('No filename in <TMPL_INCLUDE>.');
                }
                $this->_include_level++;
                if ($this->_include_level > $this->_max_include) {
                    # Protection against infinite recursive includes.
                    # Do not include the template.
                    $skip_params = TRUE;
                    _DEB("INCLUDE: LIMIT REACHED: $filename");
                }
                else {
                    # Include the template.
                    $skip_params = FALSE;
                    $include_file = $this->_include_path.'/'.$filename;
                    array_push($this->_include_files, $include_file);
                    $include_data = $this->read($include_file);
                    $include_tokens =& $this->parse($include_data);
                                        
                    # Append the tokens from the included template to actual
                    # position in the tokens list, replacing the TMPL_INCLUDE
                    # token and its parameters.
                    array_splice($tokens, $i, _PARAMS_NUMBER + 1, $include_tokens);
                    
                    $i = $i + count($include_tokens);
                    _DEB("INCLUDED: $filename");
                    continue;    # Do not increment $i below.
                }
            }

            $i++;
        }   # end of the main while loop
        
        if ($this->_include_level > 0) {
            $this->_include_level--;
        }
    }
    
    function &tokenize($template_data) {
        # Split the template into tokens separated by template statements.
        # The statements itself and associated parameters are also separately
        # included in the resulting list of tokens. Returns a REFERENCE to
        # array of the tokens.    
        
        _DEB('TOKENIZING TEMPLATE');
        $statements_pat = '
            (?:^[ \t]+)?               # eat spaces, tabs (opt.)
            (<
             (?:!--[ ])?               # comment start + space (opt.)
             /?[Tt][Mm][Pp][Ll]_[a-zA-Z]+    # closing slash "/" (opt.) + statement
             [^>]*  # this spans also comments ending (--)
             >)
            (?:\r?\n)?                 # eat trailing newline (opt.)
        ';
        $NO_LIMIT = -1;
        $split = preg_split("|$statements_pat|xm", $template_data, $NO_LIMIT,
                            PREG_SPLIT_DELIM_CAPTURE);
        $tokens = array();
        foreach ($split as $statement) {
            if (substr($statement, 0, 6) == '<TMPL_' ||
                substr($statement, 0, 7) == '</TMPL_' ||
                substr($statement, 0, 10) == '<!-- TMPL_' ||
                substr($statement, 0, 11) == '<!-- /TMPL_' ||
                substr($statement, 0, 6) == '<tmpl_' ||
                substr($statement, 0, 7) == '</tmpl_' ||
                substr($statement, 0, 10) == '<!-- tmpl_' ||
                substr($statement, 0, 11) == '<!-- /tmpl_') {
                # Processing statement.
                $statement = $this->strip_brackets($statement);
                $params = preg_split("|\s+|", $statement, $NO_LIMIT,
                                     PREG_SPLIT_NO_EMPTY);
                $directive = BIN_PRETOKEN;
                $directive .= $this->find_directive($params);
                if ($directive == BIN_PRETOKEN.INT_TMPL_STATIC) {
                    $stvar = $this->find_name($params);
                    $escape = $this->find_param('ESCAPE', $params);
                    if (isset($this->_static[$stvar])) {
                        array_push($tokens, TemplateProcessor::escape(TRUE, 
                            $this->_static[$stvar], $escape));
                    }
                    else {
                        __error("Cannot find STATIC data for '$st'.");
                    }
                }
                else if ($directive == BIN_PRETOKEN.INT_TMPL_SELECT || 
                         $directive == BIN_PRETOKEN.INT_TMPL_CUSTSELECT ||
                         $directive == BIN_PRETOKEN.INT_TMPL_RADIO ||
                         $directive == BIN_PRETOKEN.INT_TMPL_CUSTRADIO ||
                         $directive == BIN_PRETOKEN.INT_TMPL_CHECKBOX ||
                         $directive == BIN_PRETOKEN.INT_TMPL_CUSTCHECKBOX ||
                         $directive == BIN_PRETOKEN.INT_TMPL_ENDCUSTRADIO ||
                         $directive == BIN_PRETOKEN.INT_TMPL_ENDCHECKBOX ||
                         $directive == BIN_PRETOKEN.INT_TMPL_ENDCUSTCHECKBOX ||
                         $directive == BIN_PRETOKEN.INT_TMPL_OPTION ||
                         $directive == BIN_PRETOKEN.INT_TMPL_TEXT) {
                    array_push($tokens, $directive);
                    array_push($tokens, $this->find_name($params));
                    array_push($tokens, $this->find_extra($params));
                    array_push($tokens, NULL);
                }
                else {
                    # normal template command
                    array_push($tokens, $directive);
                    array_push($tokens, $this->find_name($params));
                    array_push($tokens, $this->find_param('ESCAPE', $params));
                    array_push($tokens, $this->find_param('GLOBAL', $params));
                }
            }
            else {
                # "Normal" template data.
                # here is also the gettext processing
                if ($this->_optimize_spaces) {
                    $str = '';
                    $lines = explode("\n", $statement);
                    $lines_cnt = count($lines);
                    for ($l = 0; $l < $lines_cnt; $l++) {
                        $str .= preg_replace('/^\s+/', ' ', $lines[$l]);
                        if ($l < $lines_cnt - 1) {
                            $str .= "\n";
                        }
                    }
                }
                else {
                    $str = $statement;
                }
                if ($this->_gettext) {
                    _DEB("PARSING GETTEXT STRINGS");
                    $this->gettext_tokens($tokens, $str);
                }
                else {
                    array_push($tokens, $str);
                }
            }
        }
        return $tokens;
    }

    function gettext_tokens(&$tokens, $str) {
        # Find gettext strings and return appropriate array of
        # processing tokens.
        $chars = preg_split('//', $str, -1, PREG_SPLIT_NO_EMPTY);
        $escaped = FALSE;
        $gt_mode = FALSE;
        $i = 0;
        $buf = '';

        while(TRUE) {
            if ($i == count($chars)) {
                break;
            }
            if ($chars[$i] == '\\') {
                $escaped = FALSE;
                if ($chars[$i+1] == '\\') {
                    $buf .= '\\';
                    $i += 2;
                    continue;
                }
                else if ($chars[$i+1] == '[' || $chars[$i+1] == ']') {
                    $escaped = TRUE;
                }
                else {
                    $buf .= '\\';
                }
            }
            else if ($chars[$i] == '[' && $chars[$i+1] == '[') {
                if ($gt_mode) {
                    if ($escaped) {
                        $escaped = FALSE;
                        $buf .= '[';
                    }
                    else {
                        $buf .= '[';
                    }
                }
                else {
                    if ($escaped) {
                        $escaped = FALSE;
                        $buf .= '[';
                    }
                    else {
                        array_push($tokens, $buf);
                        $buf = '';
                        $gt_mode = TRUE;
                        $i += 2;
                        continue;
                    }
                }
            }
            else if ($chars[$i] == ']' && $chars[$i+1] == ']') {
                if ($gt_mode) {
                    if ($escaped) {
                        $escaped = FALSE;
                        $buf .= ']';
                    }
                    else {
                        $this->add_gettext_token($tokens, $buf);
                        $buf = '';
                        $gt_mode = FALSE;
                        $i += 2;
                        continue;
                    }
                }
                else {
                    if ($escaped) {
                        $escaped = FALSE;
                        $buf .= ']';
                    }
                    else {
                        $buf .= ']';
                    }
                }
            }
            else {
                $escaped = FALSE;
                $buf .= $chars[$i];
            }
            $i++;
        }
        
        if ($buf) {
            array_push($tokens, $buf);
        }
    }

    function add_gettext_token(&$tokens, $str) {
        _DEB("GETTEXT PARSER: TOKEN: $str");
        array_push($tokens, BIN_PRETOKEN.INT_TMPL_GETTEXT);
        array_push($tokens, $str);
        array_push($tokens, NULL);
        array_push($tokens, NULL);
    }

    function strip_brackets($statement) {
        # Strip HTML brackets (with optional HTML comments) from the
        # beggining and from the end of a statement.       
        if (substr($statement, 0, 10) == '<!-- TMPL_' ||                
            substr($statement, 0, 11) == '<!-- /TMPL_' ||
            substr($statement, 0, 10) == '<!-- tmpl_' ||                
            substr($statement, 0, 11) == '<!-- /tmpl_') {
            return substr($statement, 5, strlen($statement) - (5 + 4));
        }
        else {
            return substr($statement, 1, strlen($statement) - (1 + 1));
        }
    }
    
    function find_directive(&$params) {
        # Extract processing directive (TMPL_*) from a statement.
        $directive = $params[0];
        array_shift($params);
        _DEB("TOKENIZER: DIRECTIVE: '$directive'");
        switch ($directive) {
            case 'TMPL_VAR':        return INT_TMPL_VAR;
            case 'TMPL_LOOP':       return INT_TMPL_LOOP;
            case '/TMPL_LOOP':      return INT_TMPL_ENDLOOP;
            case 'TMPL_IF':         return INT_TMPL_IF;
            case '/TMPL_IF':        return INT_TMPL_ENDIF;
            case 'TMPL_UNLESS':     return INT_TMPL_UNLESS;
            case '/TMPL_UNLESS':    return INT_TMPL_ENDUNLESS;
            case 'TMPL_ELSE':       return INT_TMPL_ELSE;
            case 'TMPL_BOUNDARY':   return INT_TMPL_BOUNDARY;
            case 'TMPL_INCLUDE':    return INT_TMPL_INCLUDE;
            case 'TMPL_GETTEXT':    return INT_TMPL_GETTEXT;
            case 'TMPL_UNLESS':     return INT_TMPL_UNLESS;

            case 'TMPL_TEXT':           return INT_TMPL_TEXT;
            case 'TMPL_SELECT':         return INT_TMPL_SELECT;
            case 'TMPL_CHECKBOX':       return INT_TMPL_CHECKBOX;
            case 'TMPL_RADIO':          return INT_TMPL_RADIO;
            case 'TMPL_CUSTSELECT':     return INT_TMPL_CUSTSELECT;
            case '/TMPL_CUSTSELECT':    return INT_TMPL_ENDCUSTSELECT;
            case 'TMPL_CUSTCHECKBOX':   return INT_TMPL_CUSTCHECKBOX;
            case '/TMPL_CUSTCHECKBOX':  return INT_TMPL_ENDCUSTCHECKBOX;
            case 'TMPL_CUSTRADIO':      return INT_TMPL_CUSTRADIO;
            case '/TMPL_CUSTRADIO':     return INT_TMPL_ENDCUSTRADIO;
            case 'TMPL_OPTION':         return INT_TMPL_OPTION;
            
            case 'TMPL_STATIC':         return INT_TMPL_STATIC;

            case 'tmpl_var':        return INT_TMPL_VAR;
            case 'tmpl_loop':       return INT_TMPL_LOOP;
            case '/tmpl_loop':      return INT_TMPL_ENDLOOP;
            case 'tmpl_if':         return INT_TMPL_IF;
            case '/tmpl_if':        return INT_TMPL_ENDIF;
            case 'tmpl_unless':     return INT_TMPL_UNLESS;
            case '/tmpl_unless':    return INT_TMPL_ENDUNLESS;
            case 'tmpl_else':       return INT_TMPL_ELSE;
            case 'tmpl_boundary':   return INT_TMPL_BOUNDARY;
            case 'tmpl_include':    return INT_TMPL_INCLUDE;
            case 'tmpl_gettext':    return INT_TMPL_GETTEXT;
            case 'tmpl_unless':     return INT_TMPL_UNLESS;

            case 'tmpl_text':           return INT_TMPL_TEXT;
            case 'tmpl_select':         return INT_TMPL_SELECT;
            case 'tmpl_checkbox':       return INT_TMPL_CHECKBOX;
            case 'tmpl_radio':          return INT_TMPL_RADIO;
            case 'tmpl_custselect':     return INT_TMPL_CUSTSELECT;
            case '/tmpl_custselect':    return INT_TMPL_ENDCUSTSELECT;
            case 'tmpl_custcheckbox':   return INT_TMPL_CUSTCHECKBOX;
            case '/tmpl_custcheckbox':  return INT_TMPL_ENDCUSTCHECKBOX;
            case 'tmpl_custradio':      return INT_TMPL_CUSTRADIO;
            case '/tmpl_custradio':     return INT_TMPL_ENDCUSTRADIO;
            case 'tmpl_option':         return INT_TMPL_OPTION;

            case 'tmpl_static':         return INT_TMPL_STATIC;
        }
    }
    
    function find_name(&$params) {
        # Extract identifier from a statement. The identifier can be
        # specified both implicitely or explicitely as a 'NAME' parameter.
        $name = NULL;
        if (! strstr($params[0], '=')) {
            # implicit identifier
            $name = $params[0];
            array_shift($params);
        }
        else {
            # explicit identifier as a 'NAME' parameter
            $name = $this->find_param('NAME', $params);
        }
        _DEB("TOKENIZER: NAME: '$name'");
        return $name;
    }
    
    function find_param($param, &$params) {
        # Extract value of parameter from a statement.    
        $ret_value = NULL;
        foreach ($params as $pair) {
            list($name, $value) = explode('=', $pair);
            if (! $name || ! $value) {
                __error('Syntax error in template.');
            }
            if ($name == $param) {
                if ($value{0} == '"') {
                    # The value is in double quotes.
                    $ret_value = substr($value, 1, strlen($value) - 2);
                }
                else {
                    # The value is without double quotes.
                    $ret_value = $value;
                }
                _DEB("TOKENIZER: PARAM: '$param' => '$ret_value'");
                return $ret_value;
            }
        }
        _DEB("TOKENIZER: PARAM: '$param' => NOT DEFINED");
    }

    function find_extra(&$params) {
        $ret = array();
        $ex = 0;
        foreach ($params as $p) {
            if ($ex) {
                array_push($ret, $p);
            }
            else if ($p == '|') {
                $ex = 1;
            }
        }
        return implode(' ', $ret);
    }
}


##############################################
#              CLASS: Template               #
##############################################

class Template {
    # This class represents a compiled template.
    # 
    # This class provides storage and methods for the compiled template
    # and associated metadata. It's serialized by pickle if we need to
    # save the compiled template to disk in a precompiled form.
    #
    # You should never instantiate this class directly. Always use the
    # <em>TemplateManager</em> or <em>TemplateCompiler</em> classes to
    # create the instances of this class.
    #
    # The only method which you can directly use is the <em>is_uptodate</em>
    # method.

    var $_version;
    var $_file;
    var $_tokens;
    var $_compile_params;
    var $_mtime;
    var $_include_mtimes;
    
    function Template($version, $file, &$include_files, &$tokens,
                      &$compile_params) {
        $this->_version = $version;
        $this->_file = $file;
        $this->_tokens = $tokens;
        $this->_compile_params = $compile_params;
        $this->_mtime = NULL;
        $this->_include_mtimes = array();
        
        if (! $file) {
            _DEB('TEMPLATE WAS COMPILED FROM A STRING');
            return;
        }
        
        # Save modifitcation time of the main template file.    
        if (is_file($file)) {
            $this->_mtime = filemtime($file);
        }
        else {
            __error("Template: file does not exist: '$file'.");
        }
    
        # Save modificaton times of all included template files.
        foreach ($include_files as $inc_file) {
            if (is_file($inc_file)) {
                $this->_include_mtimes[$inc_file] = filemtime($inc_file);
            }
            else {
                __error("Template: file does not exist: '$inc_file'.");
            }
        }
        _DEB('NEW TEMPLATE CREATED');
    }
    
    function is_uptodate($compile_params=NULL) {
        # Check whether the compiled template is uptodate.
        #
        # Return true if this compiled template is uptodate.
        # Return false, if the template source file was changed on the
        # disk since it was compiled.
        # Works by comparison of modification times.
        # Also takes modification times of all included templates
        # into account.
        #
        # returns: True if the template is uptodate, false otherwise.
        # param compile_params: Only for internal use.
        # Do not use this optional parameter. It's intended only for
        # internal use by the <em>TemplateManager</em>.
        
        if (! $this->_file) {
            _DEB('TEMPLATE WAS COMPILED FROM A STRING');
            return FALSE;
        }
        
        if ($this->_version != _VERSION) {
            _DEB('TEMPLATE: VERSION NOT UPTODATE');
            return FALSE;
        }

        if ($compile_params != NULL &&
            $compile_params != $this->_compile_params) {
            _DEB('TEMPLATE: DIFFERENT COMPILATION PARAMS');
            return FALSE;
        }
    
        # Check modification times of the main template and all included
        # templates. If the included template no longer exists, then
        # the problem will be resolved when the template is recompiled.
        
        # Main template file.
        if (! (is_file($this->_file) &&
               $this->_mtime == filemtime($this->_file))) {
            _DEB("TEMPLATE: NOT UPTODATE: {$this->_file}");
            return FALSE;
        }

        # Included templates.
        foreach ($this->_include_mtimes as $file => $mtime) {
            if (! (is_file($file) && $mtime == filemtime($file))) {
                _DEB("TEMPLATE: NOT UPTODATE: $file");
                return FALSE;
            }
        }

        _DEB('TEMPLATE: UPTODATE');
        return TRUE;
    }
    
    function &tokens() {
        # Get a REFERENCE to tokens of this template.
        return $this->_tokens;
    }
    
    function file() {
        # Get filename of the main file of this template.
        return $this->_file;    
    }
}


##############################################
#           CLASS: TemplateLoop              #
##############################################

class TemplateLoop {
    var $_tp;   # template processor reference
    var $_name;
    var $_varnames;
    var $_data;
    var $_lastvar;
   
    function TemplateLoop(&$tp, &$name, &$varnames) {
        $this->_tp =& $tp;
        $this->_name =& $name;
        $this->_varnames =& $varnames;
        $this->_data = array();
        $this->_nextvar = 0;
    }

    function push() {
        $args = func_get_args();
        $tmp = array();

        if (count($args) > count($this->_varnames)) {
            __error("TemplateLoop: push: too many parameters.");
            return FALSE;
        }
       
        $this->_nextvar = 0;
        for ($i = 0; $i < count($args); $i++) {
            if (is_object($args[$i])) {
                # nested loop
                $tmp[$this->_varnames[$this->_nextvar]] =& $args[$i]->_data;
            }
            else {
                $tmp[$this->_varnames[$this->_nextvar]] =& $args[$i];
            }
            $this->_nextvar++;
        }
        array_push($this->_data, $tmp);
    }

    function add() {
        $args = func_get_args();
        $cur =& $this->_data[count($this->_data) - 1];   # last item
        
       if (count($args) > count($this->_varnames) - $this->_nextvar) {
            __error("TemplateLoop: add: too many parameters");
            return FALSE;
       }

        for ($i = 0; $i < count($args); $i++) {
            if (is_object($args[$i])) {
                # nested loop
                $cur[$this->_varnames[$this->_nextvar]] =& $args[$i]->_data;
            }
            else {
                $cur[$this->_varnames[$this->_nextvar]] =& $args[$i];
            }
            $this->_nextvar++;
        }
    }

    function commit() {
        $this->_tp->set($this->_name, $this->_data);
    }
}

?>
