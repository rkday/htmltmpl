<?
$TEST = 'static';
require('../htmltmpl.php');
if (in_array('debug', $argv)) {
    $HTMLTMPL_DEBUG = 'debug.log';
}

$man = new TemplateManager(TRUE, 5, FALSE, TRUE);
$static = array();
$static['st1var'] = 'st1data <>';
$static['st2var'] = 'st2data <>';
$man->static_data($static);
$man->watch_files(array('inc/static.inc'));
$template =& $man->prepare($TEST.'.tmpl');
$tproc = new TemplateProcessor();
$output = '';

#######################################################

$tproc->set('title', 'Template world.');
$tproc->set('greeting', 'Hello !');
$tproc->set('Loop1', array(
    array('test' => 'loopvar1'),
    array('test' => 'loopvar2')));

#######################################################

require('foot.inc');

?>
