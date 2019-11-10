Archive Repertory (module for Omeka S)
======================================

[![Build Status](https://travis-ci.org/Daniel-KM/Omeka-S-module-ArchiveRepertory.svg?branch=master)](https://travis-ci.org/Daniel-KM/Omeka-S-module-ArchiveRepertory)

[Archive Repertory] is a module for [Omeka S] that allows Omeka to keep the
original names of imported files and to put them into a simple and hierarchical
structure consisting of: "item set / item / files", in order to get readable
urls for files and to avoid an overloading of the file server.

See the example of the digitized heritage of the library of [Mines ParisTech].

This [Omeka S] module is a rewrite of the [Archive Repertory plugin] for [Omeka Classic]
by [BibLibre] with the same features as the original plugin.


Notes
-----

If a duplicate name is detected, an index is added to the filename as a suffix.
Check is done on the basename, without extension, to avoid issues with
derivative files.

Duplicate names of collections and items don’t create issues, because each file
is managed individually. Nevertheless, to use identical names is not recommended,
because files of different items or collections will be mixed in the same
folder.

Currently, when a collection is moved, files are not moved until each item is
updated. The files are still available. This avoids a long process. To
update each folder, it’s possible to batch edit items without any operation, so
a job will be launched and files will be moved automatically.

This module can be used with [Clean Url] for an improved user experience and for
a better search engine optimization.


Installation
------------

Uncompress files and rename module folder "ArchiveRepertory".

Then install it like any other Omeka module and follow the config instructions.

Tiles for big images created with the module [IIIF Server] are compatible with
this module. To enable it, just open and submit the config page of the
IIIF Server, the integration between the two modules will be registered
automatically.

For development, PhpUnit 6 is used, unlike Omeka S, that requires PhpUnit 4.8.
Example:

```
    cd  test
    wget https://phar.phpunit.de/phpunit-6.3.phar
    php phpunit-6.3.phar
```


Unicode filenames
-----------------

As Omeka S, the module works fine with filenames with Unicode characters, but
all the system, database, filesystem and php web/cli environment on the server
should be set according to this format.

An autocheck is done in the config page. You can check it too when you upload a
file with a Unicode name.

If derivative files with non Ascii names are not created, check the behavior of
the php function "escapeshellarg()", largely used in Omeka. The problem occurs
only when Omeka uses command line interface, in particular to create derivative
images or to get mime type from files. After, you have five possibilities:

- use only folder and file names with standard Ascii characters;
- set options to auto convert files and folders names to Ascii;
- change the configuration of the server if you have access to it
    To change an Apache server configuration, simply update config in the file
    "/etc/apache2/envvars", either:
    - uncomment the line `. /etc/default/locale` in order to use the system
    default locale, and add this just below to avoid numerical issues: `export LC_NUMERIC=C`.
    - or replace "export LANG=C" by "export LANG="C.UTF-8".
- add this in the beginning the "bootstrap.php" of Omeka (with your locale):
    `setlocale(LC_CTYPE, 'fr_FR.UTF-8');`
    `setlocale(LC_COLLATE, 'fr_FR.UTF-8');`
    Avoid `setlocale(LC_ALL, 'fr_FR.UTF-8')`, and keep numerical values as "C":
    `setlocale(LC_NUMERIC, 'C');`
    Else, you can use a more generic solution: `setlocale(LC_ALL, 'C.UTF-8');`
- replace every `escapeshellarg()` with \ArchiveRepertory\Helpers::escapeshellarg_special()`
  and every `basename()` with `\ArchiveRepertory\Helpers::basename_special()`.

For more explanation, try the [test file].


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Copyright
---------

* Copyright Daniel Berthereau, 2012-2019 (see [Daniel-KM] on GitHub)
* Copyright BibLibre, 2016-2017

First version of this plugin has been built for [École des Ponts ParisTech].
The upgrade for Omeka 2.0 has been built for [Mines ParisTech]. The upgrade for
Omeka S has been built by [BibLibre].


[Archive Repertory]: https://github.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Omeka S]: https://omeka.org/s
[Omeka Classic]: https://omeka.org
[Archive Repertory plugin]: https://github.com/Daniel-KM/Omeka-plugin-ArchiveRepertory
[test file]: https://gist.github.com/Daniel-KM/9754f18f9632423fb1a08909e9f01c04
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-ArchiveRepertory/issues
[Clean Url]: https://github.com/biblibre/Omeka-S-module-CleanUrl
[IIIF Server]: https://github.com/Daniel-KM/Omeka-S-module-IiifServer
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[BibLibre]: https://github.com/biblibre
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: https://patrimoine.mines-paristech.fr
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
