#!/usr/bin/env python

TEST = "else"

import sys
import os
sys.path.insert(0, "..")

from htmltmpl import Template

tmpl = Template(template = TEST + ".tmpl",
                template_path = ["."],
                compile = 0,
                debug = "debug" in sys.argv)

#######################################################

tmpl["title"] = "Stranka"
tmpl["true"] = 1
tmpl["false"] = 0

#######################################################

output = tmpl.output()

if "out" in sys.argv:
    sys.stdout.write(output)
    sys.exit(0)

res = open("%s.res" % TEST).read()

print TEST, "...",

if output == res:
    print "OK"
else:
    print "FAILED"
    open("%s.fail" % TEST, "w").write(output)
