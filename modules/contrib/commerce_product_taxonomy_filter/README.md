# Drupal 8 Commerce product taxonomy filter

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Upgrading

INTRODUCTION
------------

This module provides views filter for commerce products. 

REQUIREMENTS
------------

 * This module requires commerce, commerce_product and views module.

INSTALLATION
------------

The installation of this module is like other Drupal modules.

 1. Copy/upload the commerce_product module to the modules directory.

 2. Enable the 'commerce_product' module and desired sub-modules in 'Extend'.
   (/admin/modules)

CONFIGURATION
-------------

 * Configure your commerce product view with product has taxonomy term
 * Go to your product Views.
	> Note: Advanced -> Contextual filters -> Add -> Product: Has taxonomy term ID
