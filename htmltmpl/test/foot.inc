
output += tproc.process(template)

if "out" in sys.argv:
    sys.stdout.write(output)
    sys.exit(0)

res = open("%s.res" % TEST).read()

print(TEST, "...", end=' ')

if output == res:
    print("OK")
else:
    print("FAILED")
    open("%s.fail" % TEST, "w").write(output)
