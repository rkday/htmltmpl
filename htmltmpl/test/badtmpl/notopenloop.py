#!/usr/bin/env python

TEST = "notopenloop"

import sys
import os
from htmltmpl import Template

tmpl = Template(template = TEST + ".tmpl",
                template_path = ["."],
                compile = 0,
                debug = sys.argv.count("debug"))

#######################################################

tmpl["title"] = "Template world."
tmpl["Loop1"] = [ {} ]

#######################################################

output = tmpl.output()

if sys.argv.count("out"):
    sys.stdout.write(output)
    sys.exit(0)

res = open("%s.res" % TEST).read()

print TEST, "...",

if output == res:
    print "OK"
else:
    print "FAILED"
    open("%s.fail" % TEST, "w").write(output)

