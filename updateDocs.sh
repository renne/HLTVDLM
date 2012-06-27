#!/bin/sh

DOCDIR='doc'
echo "\n\n\n\n"
rm -rf $DOCDIR
doxygen && git add $DOCDIR && git commit -m "Documentation update."

