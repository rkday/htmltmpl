#!/usr/bin/env python

TEST = "complex"

import sys
import os
sys.path.insert(0, "..")

from htmltmpl import Template

tmpl = Template(template = TEST + ".tmpl",
                template_path = ["."],
                compile = 0,
                debug = "debug" in sys.argv)

#######################################################

tmpl["title"] = "Hello template world."
tmpl["blurb"] = 1

users = [
    { "name" : "Joe User", "age" : 18, "city" : "London", 
      "Skills" : [
        { "skill" : "computers" },
        { "skill" : "machinery" }        
    ]},
    { "name" : "Peter Nobody", "age" : 35, "city" : "Paris", 
      "Skills" : [
        { "skill" : "tennis" },
        { "skill" : "football" },
        { "skill" : "baseball" },
        { "skill" : "fishing" }
    ]},    
    { "name" : "Jack Newman", "age" : 21, "city" : "Moscow", 
      "Skills" : [
        { "skill" : "guitar" },
        { "skill" : "piano" },
        { "skill" : "flute" }        
    ]}
]

tmpl["Users"] = users

products = [
    { "key" : 12, "name" : "cake",  "selected" : 0 },
    { "key" : 45, "name" : "milk",  "selected" : 1 },
    { "key" : 78, "name" : "pizza", "selected" : 0 },
    { "key" : 32, "name" : "roll",  "selected" : 0 },
    { "key" : 98, "name" : "ham",   "selected" : 0 },
]

tmpl["Products"] = products
tmpl["Unused_loop"] = []

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
