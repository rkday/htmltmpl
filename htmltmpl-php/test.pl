#!/usr/bin/perl

# Exec all .php files located in the "test" directory.

chdir('test');
my @scripts = <*.php>;
@scripts = sort(@scripts);
my $i = 0;
foreach $script (@scripts) {
    $i++;
    print "$i ... ";
    my $out = `php $script`;
    $out =~ s/.*\r?\n\r?\n//s;
    print $out, "\n";
}
