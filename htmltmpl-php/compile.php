<?
require_once('htmltmpl.php');

array_shift($argv);
$dir = array_shift($argv);
chdir($dir);
$man = new TemplateManager(TRUE, 5, TRUE, TRUE, TRUE, TRUE);
foreach ($argv as $tmpl) {
    $man->prepare($tmpl);
}
?>
