#!/usr/bin/env python

TEST = "nestloop"

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
tmpl["Loop1"] = [
    { "Loop2" : [ {}, {} ], 
      "Loop3" : [], 
      "Loop4" : [ { "Loop6" : [
        {}, {}, {}
      ] } ], 
      "Loop5" : [] },

    { "Loop2" : [], 
      "Loop3" : [ {}, {} ], 
      "Loop4" : [], 
      "Loop5" : [ {} ] }
]

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