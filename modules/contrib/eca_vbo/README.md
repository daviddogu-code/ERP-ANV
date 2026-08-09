# ECA VBO

Integrates ECA: Event - Condition - Action with Views Bulk Operations (VBO).

## 0. Contents

1. Introduction
2. Requirements
3. Installation
4. Usage

## 1. Introduction

This module makes it possible executing your ECA configuration as bulk
operation.

## 2. Requirements

This module requires besides Drupal core the latest version of ECA: Event -
Condition - Action and Views Bulk Operations.

## 3. Installation

Install the module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.

## 4. Usage

Once installed, you can create a bulk operation using ECA the following way:
* Create an ECA configuration or edit an existing one that contains the logic
  for your operation.
* Within the ECA config, add a new event "VBO: Execute Views bulk operation"
  and define an operation name, for example "My Operation". Any successor
  of this event is the logic of the bulk operation.
* Operations via ECA require you to also define access. You can define
  access by adding a further event "VBO: Custom access for Views bulk operation"
  within your ECA config. When adding this event, you can enter the same
  operation name you gave before to the event "VBO: Execute Views bulk
  operation". That way access checking is matching up with your operation. As a
  successor for this event, you can add an action "VBO: Set custom access on
  Views Bulk Operation" and enable access accordingly. With this you can
  implement your own access logic, for example with a prepended condition check.
* Save your ECA configuration.
* Now that you have an ECA config containing an event for reacting upon a
  bulk operation - plus an event that determines access - you can create a Views
  configuration, or go to an existing Views config where you want to make the
  operation available.
* In the Views configuration, add a new field "ECA bulk operations". When adding
  this field, you should see your operation to be available as selectable
  action.
  **Please note**: The operation would also show up as selectable action on
  a standard Views Bulk Operations configuration form, but it's not recommended
  to use it in that standard form, as it doesn't support the above mentioned
  access logic with operation names.

When you run an operation on an entity that comes from a row of your View,
don't forget to add an action that finally saves the entity.
