<?

# A script to fetch gettext strings from a htmltmpl template.
# The strings must be in the special htmltmpl format:
#
#   [string]
#
# It uses stdin and stdout and takes no parameters.
#
# Usage:
#
#   cat template.tmpl | php tmpl-xgettext.php | xgettext -o file.po -
#
# Copyright Tomas Styblo, htmltmpl templating engine, 2001
# http://htmltmpl.sourceforge.net/
# tripie@cpan.org
# LICENSE: GNU GPL
# CVS: $Id$

$data = '';
if (! ($in = fopen('php://stdin', 'r'))) {
    die("Cannot open input");
}
while($buf = fread($in, 1024)) {
    $data .= $buf;
}
if (! fclose($in)) {
    die("Cannot close input.");
}

$test = "
[Pokusny] [retezec]. Eskejpnuta \[pitomost]. A ted [test
na vice radcich].";

$pat = '/(?:^|[^\\\\])   # escaping backslash
         \\[             # opening paren
         (.+?)           # text in parens
         \\]             # closing paren
         /xs';
# print $pat."\n";
preg_match_all($pat, $data, $matches);
# print_r($matches);
foreach ($matches[1] as $match) {
    $match = preg_replace("/\r?\n/", '\n', $match);
    print "gettext(\"$match\")\n";
}
?>
