#!/usr/bin/env python

TEST = "nestcond"

import sys
import os
sys.path.insert(0, "..")

from htmltmpl import Template

tmpl = Template(template = TEST + ".tmpl",
                template_path = ["."],
                compile = 0,
                debug = "debug" in sys.argv)

#######################################################

tmpl["title"] = "Template world."
tmpl["greeting"] = "Hello !"

tmpl["var1"] = 1
tmpl["var2"] = 0
tmpl["var3"] = 1

tmpl["var4"] = 0
tmpl["var5"] = 0

tmpl["var6"] = 0
tmpl["var7"] = 1

tmpl["var8"] = 1
tmpl["var9"] = 0

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
