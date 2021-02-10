INTRODUCTION
-------------

This module enables functionality to persist form's data in browser's local
storage and never lose it on occasional tabs closing, browser crashes and other
disasters!

This plugin listens for the form change and keyup events and then stores 
the values of your form controls (except password input) in the local 
storage and automatically clears the storage on form submit or reset.

REQUIREMENTS
-------------

This module not requires the any module.

CONFIGURATION
-------------

Goto /admin/config/content/autosaveformsid of your drupal installation
   enter comma separated drupal form ids

INSTALLATION
-------------

1) Copy all contents of this package to your modules directory preserving
   subdirectory structure.

2) Go to Administer -> Modules to install module. If the (Drupal core) Field UI
   module is not enabled, do so.

MAINTAINERS
----------

Current maintainers:

* Rajveer singh
rajveer.gang@gmail.com
