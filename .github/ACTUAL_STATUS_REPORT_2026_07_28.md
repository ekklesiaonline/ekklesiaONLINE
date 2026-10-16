# ekklesiaONLINE – Actual Status Report

Date: 2026-07-28

## Executive Summary

The codebase is materially more advanced than a starter scaffold. It contains working implementations for core modules such as member management, import, dedup/merge, guest follow-up workflow, donations/expenses, worship planning, volunteer scheduling, and multi-level dashboards.

However, it is not yet fully production-stable. The repository still has unresolved integration issues, several backlog items remain unimplemented, and the current PHPUnit run is not green.

## Overall Assessment

- Status: Feature-rich but still in a stabilization phase
- Confidence level: Moderate for core module structure, lower for full production readiness
- Main blocker: test failures and schema/permission inconsistencies rather than a lack of feature breadth

## What Is Implemented

### 1. Core platform and architecture

The application is built around a modular CodeIgniter 4 structure with Shield-based authentication and role-based access. The repository includes:

- module folders for Clergy, Diocesan, Province, MemberPortal, Treasurer, Volunteer, and Worship
- controllers, models, and views for major business modules
- support for multi-parish/diocese/province hierarchy concepts
- audit logging and permission-aware controllers

### 2. Member management

The member subsystem is one of the strongest areas in the codebase. Evidence includes:

- Member controller with list/detail flows and admin management views
- profile models and parish affiliation models
- import workflow
- member dedup/merge/hard-delete service and controller
- people/parish registry logic for portal and non-portal members

This is not just scaffolding; it is a substantial, user-facing implementation.

### 3. Guest follow-up workflow

The current follow-up system is implemented and wired to dedicated models/controllers rather than the retired legacy table approach. Evidence includes:

- FollowupWorkflow controller
- FollowupSequenceModel, FollowupActionModel, FollowupTemplateModel, FollowupLogModel
- CallTaskModel and SMS queue support
- dispatch-oriented service layer for follow-up actions

### 4. Finance

The finance module is operational for core donation and expense workflows. Evidence includes:

- Treasurer donations controller with list, CSV export, PDF export, and statement generation
- Treasurer expenses controller with list and export support
- fund and budget handling models/views
- chart-of-accounts support and budget management controllers

### 5. Worship planning

Worship planning is implemented with real CRUD-style flows for services and songs. Evidence includes:

- WorshipPlanning controller
- worship song library and service plans
- service worship item persistence and planning UI

### 6. Volunteer coordination

Volunteer scheduling and team management are present and not only stubbed. Evidence includes:

- volunteer controllers and views
- team and schedule models
- list/calendar/schedule views and reminder-oriented flows

## What Is Still Missing or Only Partial

### 1. Bank reconciliation / finance extensions

Although the schema files include bank-related tables, the application code does not appear to provide a complete bank account/reconciliation module. This remains a genuine backlog item rather than a completed feature.

### 2. Small groups module

The repository contains schema references for small groups and group membership, but the user-facing module and workflows are not implemented in the current application layer.

### 3. Bulk messaging

There is no general-purpose bulk messaging module wired into the current application. The existing messaging support is follow-up-oriented rather than broad communications.

### 4. Membership pathway tracking

The documentation describes planned pathway-stage tracking, but the current codebase does not show a full pathway-stage implementation in the main member workflow.

### 5. Leadership appointment gate

The documentation identifies good-standing enforcement for leadership positions as a planned enhancement; the current codebase does not show a hard enforcement gate at insertion time.

### 6. Guest-to-member conversion pre-fill

The repository notes this gap explicitly. The conversion flow exists, but the create-member form is not fully pre-filled from the flash data in the current implementation path.

## Verification Results

A fresh PHPUnit run was executed with:

- command: ./vendor/bin/phpunit --colors=never
- result: 310 tests executed with 11 failures

### Observed failures

The failing areas include:

- Province/Diocese admin management permission tests
- encryption-at-rest tests
- member access service tests
- profile locale/profile registration integration tests

The failures point to real integration gaps, especially around:

- permission/bootstrap state
- database schema expectations
- encryption storage and lookup behavior
- member provisioning persistence

## Evidence-Based Conclusion

The application should be described as a partially complete, feature-rich business system rather than a blank or early prototype.

The strongest evidence is that the repo contains working implementations for many major modules, and the codebase is organized around a real product architecture. The weakest evidence is that the current test suite is not passing, which means the system still needs stabilization work before it should be treated as fully production-ready.

## Recommended Priority Order

1. Stabilize failing integration tests
2. Resolve schema/column mismatches and provisioning errors
3. Finish the highest-risk backlog items: bank reconciliation, small groups, bulk messaging
4. Harden permission and role behavior around province/diocese/parish scope
5. Close the remaining governance and pathway features documented in the backlog

## Bottom Line

- Strong implementation breadth: yes
- Strong production readiness: not yet
- Main remaining work: stabilization, schema consistency, and finishing the remaining modules identified in the backlog
