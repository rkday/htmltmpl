#!/usr/bin/env python

TEST = "loop"

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
tmpl["Boys"] = [
    { "name" : "Tomas",  "age" : 19 },
    { "name" : "Pavel",  "age" : 34 },
    { "name" : "Janek",  "age" : 67 },
    { "name" : "Martin", "age" : 43 },
    { "name" : "Viktor", "age" : 78 },
    { "name" : "Marian", "age" : 90 },
    { "name" : "Prokop", "age" : 23 },
    { "name" : "Honzik", "age" : 46 },
    { "name" : "Brudra", "age" : 64 },
    { "name" : "Marek",  "age" : 54 },
    { "name" : "Peter",  "age" : 42 },
    { "name" : "Beda",   "age" : 87 }
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
