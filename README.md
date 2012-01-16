# BOOKMETA - a book metadata extractor

## INTRO
A small script that extracts book metadata from the most comprehensive modern Greek book database: http://biblionet.gr.
Metadata are returned as a json object if no additional parameter is provided.
For the time the only additional parameter supported is 'format' and the only valid values are: 'json' (:default) and 'html'

## INSTALLATION AND USE
Deploy all the php files provided in the package on a web server with php support and the make get requests as follows:
http://localhost/bookmeta/index.php?isbn=<someisbn>
If you want to test the output, switch on the html option
[try](http://localhost/bookmeta/index.php?isbn=<someisbn>&format=html)
