<?
require('../../htmltmpl.php');

setlocale(LC_MESSAGES, "en_US");
bindtextdomain('test', './locale');
textdomain('test');

# $HTMLTMPL_DEBUG='debug.log';
$man = new TemplateManager(TRUE, 5, FALSE, TRUE, TRUE);
$tmpl = $man->prepare('gettext.tmpl'); 
$tproc = new TemplateProcessor(TRUE, TRUE, FALSE, "my_gettext");
$tproc->set('title', 'Gettext test page');
print $tproc->process($tmpl);

function my_gettext($text) {
    return "hovno";
}

?>
