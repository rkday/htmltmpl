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

    Copyright (c) 2001 Tomas Styblo, tripie@cpan.org

    WEBSITE:        http://htmltmpl.sourceforge.net/
    LICENSE:        GNU GPL
    LICENSE-URL:    http://www.gnu.org/licenses/gpl.html
    CVS:            $Id$
*/

define('_VERSION', 1.01);
define('_AUTHOR', 'Tomas Styblo (tripie@cpan.org)');

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
            exit;
        }
        flock($debug_log, LOCK_EX);
        fputs($debug_log, $str._DEBUG_NEWLINE_SEP);
        flock($debug_log, LOCK_UN);
        if (! fclose($debug_log)) {
            __error('Cannot close debugging log.');
            exit;
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
    
    function TemplateManager($include=TRUE, $max_include=5, $precompile=TRUE,
                             $comments=TRUE) {
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
        _DEB('INIT DONE');
    }
    
    function &prepare($file) {
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
    
        $compiled = NULL;
        if ($this->_precompile) {
            if ($this->is_precompiled($file)) {
                $precompiled =& $this->load_precompiled($file);
                if (! $precompiled) {
                    _DEB('PRECOMPILED: FORCED RECOMPILATION');
                    $compiled =& $this->compile($file);
                    $this->save_precompiled($compiled);
                }
                else {
                    $compile_params = array($this->_include,
                                            $this->_max_include,
                                            $this->_comments);
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
            else {
                _DEB('PRECOMPILED: NOT PRECOMPILED');
                $compiled =& $this->compile($file);
                $this->save_precompiled($compiled);
            }
        }
        else {
            _DEB('PRECOMPILATION DISABLED');
            $compiled =& $this->compile($file);
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
    
    ##############################################
    #              PRIVATE METHODS               #
    ##############################################  
    
    function &compile($file) {
        # Compile the template from a file.
        # The method returns a REFERENCE to the template. You must use the
        # reference assignment when calling the method (=&) !        
        $tmplc = new TemplateCompiler($this->_include, $this->_max_include,
                                      $this->_comments);
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
            exit;
        }
        
        # Lock, read and unlock it.
        flock($precompiled_file, LOCK_SH);
        $precompiled_data = fread($precompiled_file, filesize($filename));
        flock($precompiled_file, LOCK_UN);
        
        # Close it.
        if (! fclose($precompiled_file)) {
            __error("Cannot close precompiled template '$filename'.");
            exit;
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
                exit;
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
            exit;
        }
        
        # Prevent script interruption while we hold a file lock.
        $old_ignore_user_abort = ignore_user_abort();
        ignore_user_abort(1);

        # Open the file.
        if (! ($precompiled_file = fopen($filename, 'wb'))) {
            __error("Cannot save precompiled template '$filename'.");
            exit;
        }
        
        # Lock, read and unlock it.
        flock($precompiled_file, LOCK_EX);
        fwrite($precompiled_file, serialize($template));
        flock($precompiled_file, LOCK_UN);
        
        # Close it.
        if (! fclose($precompiled_file)) {
            __error("Cannot close precompiled template '$filename'.");
            exit;
        }
        ignore_user_abort($old_ignore_user_abort);
        _DEB('SAVING PRECOMPILED');
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
    
    function TemplateProcessor($html_escape=TRUE, $magic_vars=TRUE,
                               $global_vars=FALSE) {
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
    }
    
    function set($var, $value) {
        # Associate a value with top-level template variable or loop.
        #
        # A template identifier can represent either an ordinary variable
        # (string) or a loop.
        #
        # To assign a value to a string identifier pass a scalar
        # as the 'value' parameter. This scalar will be automatically
        # converted to string.
        #
        # To assign a value to a loop identifier pass a list of mappings as
        # the 'value' parameter. The engine iterates over this list and
        # assigns values from the mappings to variables in a template loop
        # block if a key in the mapping corresponds to a name of a variable
        # in the loop block. The number of mappings contained in this list
        # is equal to number of times the loop block is repeated in the
        # output.
        #
        # returns: No return value.
        # param var: Name of template variable or loop.
        # param value: The value to associate.

        # The correctness of character case is verified only for top-level
        # variables.
        if (! is_array($value)) {
            # template top-level ordinary variable
            if ($var != strtolower($var)) {
                __error("Invalid variable name '$var'.");
                exit;
            }
        }
        else {
            # template top-level loop
            $first_char = $var{0};
            $rest = substr($var, 1);
            if ($first_char != strtoupper($first_char) ||
                $rest != strtolower($rest)) {
                __error("Invalid loop name '$var'.");
                exit;
            }
        }
        $this->_vars[$var] = $value;
        _DEB("VALUE SET: $var");
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
            exit;
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
            if (substr($token, 0, 6) == '<TMPL_' ||
                substr($token, 0, 7) == '</TMPL_') {

                if ($token == '<TMPL_VAR') {
                    # TMPL_VARs should be first. They are the most common.
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_VAR>.');
                        exit;
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
                        $out .= $this->escape($value, $escape);
                        _DEB("VAR: $var");
                    }
                }

                elseif ($token == '<TMPL_LOOP') {
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_LOOP>.');
                        exit;
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

                elseif ($token == '<TMPL_IF') {
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_IF>.');
                        exit;
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

                elseif ($token == '<TMPL_UNLESS') {
                    $var = $tokens[$i + _PARAM_NAME];
                    if (! $var) {
                        __error('No identifier in <TMPL_UNLESS>.');
                        exit;
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

                elseif ($token == '</TMPL_LOOP') {
                    $skip_params = TRUE;
                    if (! $loop_name) {
                        __error('Unmatched </TMPL_LOOP>.');
                        exit;
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

                elseif ($token == '</TMPL_IF') {
                    $skip_params = TRUE;
                    if (! $output_control) {
                        __error('Unmatched </TMPL_IF>.');
                        exit;
                    }
                    array_pop($output_control);
                    _DEB('IF: END');
                }    

                elseif ($token == '</TMPL_UNLESS') {
                    $skip_params = TRUE;
                    if (! $output_control) {
                        __error('Unmatched </TMPL_UNLESS>.');
                        exit;
                    }
                    array_pop($output_control);
                    _DEB('UNLESS: END');
                }

                elseif ($token == '<TMPL_ELSE') {
                    $skip_params = TRUE;
                    if (! $output_control) {
                        __error('Unmatched <TMPL_ELSE>.');
                        exit;
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
                        exit;
                    }                
                }

                elseif ($token == '<TMPL_BOUNDARY') {
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

                elseif ($token == '<TMPL_INCLUDE') {
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

                else {
                    # Unknown processing directive.
                    __error("Invalid statement $token>.");
                    exit;
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
            exit;
        }
        if ($output_control) {
            __error('Missing </TMPL_IF> or </TMPL_UNLESS>.');
            exit;
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
            
        if ($scope[$var]) {
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
            if ($var{0} == strtoupper($var{0})) {
                # This is a loop name.
                # Return zero, because the user wants to know number
                # of its passes.
                return 0;
            }
            else {
                return '';
            }
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
                    exit;
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
            exit;
        }    
    }
    
    function escape($str, $override=NULL) {
        # Escape a string either by HTML escaping or by URL escaping.
        if (($this->_html_escape && $override != 'NONE' && $override != '0' &&
             $override != 'URL') || $override == 'HTML' || $override == '1') {
            return htmlspecialchars($str);
        }
        elseif ($override == 'URL') {
            return urlencode($str);
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
    var $_include_files;
    var $_include_level;
    var $_include_path;
    
    function TemplateCompiler($include=TRUE, $max_include=5, $comments=TRUE) {
        # Constructor.
        #
        # param include: Enable or disable included templates.
        # param max_include: Maximum depth of nested inclusions
        # param comments: Enable or disable template comments.
        
        $this->_include = $include;
        $this->_max_include = $max_include;
        $this->_comments = $comments;
        
        # This is a list of filenames of all included templates.
        # It's modified by the include_templates() method.
        $this->_include_files = array();
        
        # This is a counter of current inclusion depth. It's used to prevent
        # infinite recursive includes.
        $this->_include_level = 0;
        $this->_include_path = NULL;
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
                                $this->_comments);
        return new Template(_VERSION, $file, $this->_include_files,
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
                                $this->_comments);
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
            exit;
        }
        
        # Read it.
        if (! ($template_file = fopen($filename, 'r'))) {
            __error("Cannot open template '$filename'.");
            exit;
        }
        $data = fread($template_file, filesize($filename));
        if (! fclose($template_file)) {
            __error("Cannot close template '$filename'.");
            exit;
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
            if ($token == '<TMPL_INCLUDE') {
                $filename = $tokens[$i + _PARAM_NAME];
                if (! $filename) {
                    __error('No filename in <TMPL_INCLUDE>.');
                    exit;
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
             /?TMPL_[A-Z]+             # closing slash "/" (opt.) + statement
             [ a-zA-Z0-9"/.=:_\\\\-]*  # this spans also comments ending (--)
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
                substr($statement, 0, 11) == '<!-- /TMPL_') {
                # Processing statement.
                $statement = $this->strip_brackets($statement);
                $params = preg_split("|\s+|", $statement, $NO_LIMIT,
                                     PREG_SPLIT_NO_EMPTY);
                array_push($tokens, $this->find_directive($params));
                array_push($tokens, $this->find_name($params));
                array_push($tokens, $this->find_param('ESCAPE', $params));
                array_push($tokens, $this->find_param('GLOBAL', $params));
            }
            else {
                # "Normal" template data.
                array_push($tokens, $statement);
            }
        }

        return $tokens;
    }

    function strip_brackets($statement) {
        # Strip HTML brackets (with optional HTML comments) from the
        # beggining and from the end of a statement.       
        if (substr($statement, 0, 10) == '<!-- TMPL_' ||                
            substr($statement, 0, 11) == '<!-- /TMPL_') {
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
        return '<'.$directive;
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
                exit;
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
            exit;
        }
    
        # Save modificaton times of all included template files.
        foreach ($include_files as $inc_file) {
            if (is_file($inc_file)) {
                $this->_include_mtimes[$inc_file] = filemtime($inc_file);
            }
            else {
                __error("Template: file does not exist: '$inc_file'.");
                exit;
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

?>
