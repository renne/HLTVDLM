#!/bin/sh

DOCDIR='html'
echo "\n\n\n\n"
rm -rf $DOCDIR
doxygen && git add $DOCDIR && git commit -a -m "Documentation update."

