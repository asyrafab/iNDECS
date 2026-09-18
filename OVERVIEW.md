# iNDECS — Intergrated Development Control System

> **Bahasa Melayu:** Sistem Bersepada Kawalan Pembangunan

> **Tagline:** Satu Platform, Pelbagai Permohonan Pembangunan

## Table of Contents

**Part I — Vision and Requirements**
[1. Project Overview](#1-project-overview) · [2. Background](#2-background) · [3. Project Objectives](#3-project-objectives)

**Part II — Design Principles and Data Model**
[4. Core Design Principles](#4-core-design-principles) · [5. System Identity](#5-system-identity) · [6. Conceptual Data Model](#6-conceptual-data-model) · [7. Development Case](#7-development-case) · [8. Application Record](#8-application-record) · [9. Application Relationships](#9-application-relationships)

**Part III — Core and Departmental Modules**
[10. Shared Core Modules](#10-shared-core-modules) · [11. Department-Specific Modules](#11-department-specific-modules)

**Part IV — Workflow Engine**
[12. Configurable Workflow Engine](#12-configurable-workflow-engine) · [13. Workflow Examples](#13-workflow-examples) · [14. Conditional Workflow Examples](#14-conditional-workflow-examples)

**Part V — Data, Architecture and Technology**
[15. Suggested Database Structure](#15-suggested-database-structure) · [16. System Architecture](#16-system-architecture) · [17. Recommended Technology Approach](#17-recommended-technology-approach)

**Part VI — Access, Security and Reference Data**
[18. User Roles and Permissions](#18-user-roles-and-permissions) · [19. Multiuser Editing and Data Protection](#19-multiuser-editing-and-data-protection) · [20. Lookup and Reference Data](#20-lookup-and-reference-data) · [21. Excel Migration and Integration](#21-excel-migration-and-integration)

**Part VII — Intelligent Automation**
[22. AI Integration Strategy](#22-ai-integration-strategy)

**Part VIII — Interface, Validation and Delivery**
[23. Recommended User Interface](#23-recommended-user-interface) · [24. Example Scenarios](#24-example-scenarios) · [25. Non-Functional Requirements](#25-non-functional-requirements) · [26. Suggested Implementation Phases](#26-suggested-implementation-phases) · [27. Future Enhancements](#27-future-enhancements) · [28. Final System Vision](#28-final-system-vision) · [29. Key Principles](#29-key-principles)

---

# Part I — Vision and Requirements

## 1. Project Overview

### Executive Summary

iNDECS is a proposed central platform for managing development-control applications — Permohonan Kebenaran Merancang (PKM), Pelan Bangunan (PB), Pelan Kerja Tanah (PKT), Pelan Kerja Parit (PKP), Pelan Landskap (PL), and future application types — currently processed separately across MBSP's technical departments using a mix of GIS systems, Excel files and departmental records. The platform's core design principle is flexibility: every application type can stand alone, related applications can be linked through an optional development case, and no single workflow or sequence is imposed on every development. Sections 1–21 define the vision, data model, shared and department-specific modules, workflow engine, database structure, architecture and security model. Section 22 adds an AI Integration Strategy — document classification, data extraction, compliance pre-checks and predictive support — layered on top of this foundation under a strict human-in-the-loop principle: AI assists officers, it does not replace their decisions.

---

**iNDECS — Intergrated Development Control System** (**Sistem Bersepada Kawalan Pembangunan**) is proposed as a central, GIS-enabled platform for managing development-control applications processed by different technical departments within MBSP.

The system is not limited to **Permohonan Kebenaran Merancang (PKM)**. It is intended to support multiple application types, including:

- Permohonan Kebenaran Merancang (PKM)
- Pelan Bangunan (PB)
- Pelan Kerja Tanah (PKT)
- Pelan Kerja Parit (PKP)
- Pelan Landskap (PL)
- Other technical development applications introduced in the future

Each application type may have:

- Different responsible departments
- Different application data
- Different workflow stages
- Different approval requirements
- Different document requirements
- Different GIS data
- Different fee structures
- Different technical review processes

The system must therefore be configurable and must not force every application to follow the same process.

---

## 2. Background

Development-related applications are currently processed by several technical departments.

Examples include:

| Application Type | Main Department |
|---|---|
| Permohonan Kebenaran Merancang | Jabatan Perancang Bandar |
| Pelan Bangunan | Jabatan Bangunan |
| Pelan Kerja Tanah | Jabatan Perancang Bandar and Jabatan Kejuruteraan |
| Pelan Kerja Parit | Jabatan Kejuruteraan |
| Pelan Landskap | Jabatan Landskap |

At present, GIS information may be stored in SQL or ArcGIS, while supporting information such as payment status, file registration, file movement and departmental records may still be managed in Excel or separate systems.

This creates several challenges:

- Data duplication
- Difficulty tracking the latest application status
- Inconsistent data structures between departments
- Limited multiuser editing
- Difficulty identifying related applications
- Lack of a complete application history
- Limited auditability
- Manual reporting
- Repeated entry of applicant, developer, lot and site information
- Difficulty monitoring applications across departments

iNDECS is intended to provide a shared platform while allowing each department to retain its own application forms, workflow and authority.

---

## 3. Project Objectives

The main objectives of iNDECS are to:

1. Provide a central platform for development-control application data entry.
2. Support multiple application types and technical departments.
3. Allow each application to operate independently.
4. Allow related applications to be linked when necessary.
5. Support different workflows for different application types.
6. Integrate spatial and non-spatial application information.
7. Improve multiuser access and departmental collaboration.
8. Reduce reliance on manually maintained Excel files.
9. Maintain complete status, task, file movement and audit histories.
10. Support dashboards, reporting and management monitoring.
11. Provide a scalable platform for future development application types.
12. Improve data consistency and reduce duplicate data entry.

---

# Part II — Design Principles and Data Model

## 4. Core Design Principles

### 4.1 Every Application Can Stand Alone

The system must allow each application to exist independently.

Examples:

```text
PKM only
PB only
PKT only
PKP only
PL only
```

A standalone application must not require a PKM record, development case or related technical application unless the actual process requires one.

---

### 4.2 Applications May Be Linked

Applications relating to the same development may be grouped or linked.

Examples:

```text
PKM + PB
PKM + PKT
PKM + PB + PKT
PB + PL
PKT + PKP
PKM + PB + PKT + PKP + PL
```

The system must support any valid combination without assuming that all application types will exist.

---

### 4.3 No Universal Application Sequence

The system must not enforce the following as a fixed sequence:

```text
PKM
→ Pelan Bangunan
→ Pelan Kerja Tanah
→ Pelan Kerja Parit
→ Pelan Landskap
```

Some developments may follow part of this sequence, while others may involve only one application or several applications processed concurrently.

---

### 4.4 Different Application Types Have Different Workflows

Each application type must have its own configurable workflow.

For example:

- PKM may require a committee decision.
- Pelan Bangunan may require plan validation and endorsement.
- PKT may require parallel review by Planning and Engineering.
- Pelan Landskap may require maintenance-condition monitoring.
- A minor technical application may require only internal assessment and approval.

---

### 4.5 Shared Core, Separate Departmental Data

The platform should provide common modules for all applications, while application-specific information is stored separately.

```text
iNDECS Core
|
+-- PKM Module
+-- Pelan Bangunan Module
+-- Pelan Kerja Tanah Module
+-- Pelan Kerja Parit Module
+-- Pelan Landskap Module
+-- Future Modules
```

---

## 5. System Identity

### Brand Name

**iNDECS**

### English Name

**Intergrated Development Control System**

### Bahasa Melayu

**Sistem Bersepada Kawalan Pembangunan**

### Suggested Tagline

> **Satu Platform, Pelbagai Permohonan Pembangunan**

---

## 6. Conceptual Data Model

The system should be centred around the individual application.

```text
APPLICATION
|
+-- Application Type
+-- Applicant
+-- Developer
+-- Principal Submitting Person
+-- Consultant
+-- Site and Lots
+-- GIS Geometry
+-- Documents
+-- Fees and Payments
+-- File Registration
+-- File Movement
+-- Departmental Processing
+-- Technical Reviews
+-- Tasks and Assignments
+-- Meetings
+-- Decisions
+-- Approvals
+-- Related Applications
+-- Status History
+-- Audit History
```

Applications may optionally be grouped under a development case.

```text
DEVELOPMENT CASE
|
+-- PKM Application
+-- Pelan Bangunan Application
+-- Pelan Kerja Tanah Application
+-- Pelan Kerja Parit Application
+-- Pelan Landskap Application
```

The development case is optional.

---

## 7. Development Case

A development case represents the overall development proposal.

It may be used when several applications relate to the same project.

Example:

```text
Development Case:
Proposed Mixed Development at Lots 101, 102 and 103

Related Applications:
- PKM/2026/00125
- PB/2026/00310
- PKT/2026/00054
- PL/2026/00021
```

A development case may also contain only one application.

```text
Development Case:
Temporary Change of Land Use at Lot 4501

Related Application:
- PKM/2026/00218
```

Suggested development-case information:

- Case reference number
- Development title
- Development description
- Applicant
- Developer
- Landowner
- Principal submitting person
- Consultant
- General site address
- General development boundary
- Common cadastral lots
- Shared documents
- Related application list
- Overall case status

The development case must remain optional.

---

## 8. Application Record

Each application should have its own complete record.

Suggested common fields:

```text
application_id
case_id
application_type_id
application_reference
external_reference
application_title
application_description
submission_date
submission_year
responsible_department
current_stage
current_status
assigned_officer
priority
created_at
created_by
updated_at
updated_by
row_version
```

The `case_id` must allow null values so that standalone applications are supported.

---

## 9. Application Relationships

Applications should be linked through a flexible relationship table.

Suggested structure:

```text
CORE_APPLICATION_RELATION
- relation_id
- application_id
- related_application_id
- relationship_type
- remarks
- effective_date
- created_at
- created_by
```

Possible relationship types:

- Related Application
- Prerequisite
- Subsequent Application
- Submitted Concurrently
- Amendment Of
- Replacement Of
- Renewal Of
- Supersedes
- Supporting Plan
- Associated Technical Plan
- Same Development Site
- Same Development Case

Example:

```text
PKM/2026/00125
|
+-- Related to PB/2026/00310
+-- Related to PKT/2026/00054
```

Another example:

```text
PKT/2026/00078
|
+-- No related application
```

---

# Part III — Core and Departmental Modules

## 10. Shared Core Modules

### 10.1 Application Registration

Common registration information should include:

- Application type
- Application category
- Application reference number
- External or OSC reference number
- Submission date
- Application title
- Applicant
- Developer
- Landowner
- Principal submitting person
- Consultant
- Responsible department
- Assigned officer
- Current status
- Current stage

---

### 10.2 Applicant and Professional Parties

The system should support different party roles, including:

- Applicant
- Developer
- Landowner
- Principal submitting person
- Architect
- Engineer
- Town planner
- Landscape architect
- Surveyor
- Contractor
- Other consultant

Suggested tables:

```text
CORE_PARTY
CORE_APPLICATION_PARTY
CORE_DEVELOPMENT_CASE_PARTY
```

The application record should preserve a snapshot of submitted party information so that historical records remain accurate even if contact details change later.

---

### 10.3 Site and Cadastral Lots

Common site information should include:

- Development address
- Lot number
- Lot type
- Section
- Mukim
- District
- State
- DUN
- Parliamentary area
- Site area
- Existing land use
- Proposed land use
- Application point
- Application boundary

A development case may contain common lots, but each application must be allowed to use a different subset.

Example:

```text
Development Case Lots:
- Lot 101
- Lot 102
- Lot 103

PKM Application:
- Lot 101
- Lot 102
- Lot 103

PKT Application:
- Lot 101
- Lot 102

Pelan Landskap Application:
- Lot 103
```

---

### 10.4 GIS Module

ArcGIS Enterprise should remain responsible for spatial data.

Suggested GIS feature classes:

```text
GIS_DEVELOPMENT_SITE
GIS_APPLICATION_SITE
GIS_APPLICATION_POINT
GIS_APPLICATION_LOT
GIS_PROPOSED_PLAN
GIS_APPROVED_PLAN
GIS_BUILDING_FOOTPRINT
GIS_EARTHWORK_AREA
GIS_DRAINAGE_ALIGNMENT
GIS_DRAINAGE_OUTFALL
GIS_LANDSCAPE_AREA
```

The system must allow:

- Map-based application search
- Geometry editing
- Lot selection
- Site-boundary display
- Proposed-plan overlay
- Approved-plan display
- Spatial validation
- Department-specific map layers
- Hide, show and reset temporary plan layers

GIS records should be linked to application records using stable identifiers such as application IDs and ArcGIS GlobalIDs.

---

### 10.5 Document Management

Documents may be attached to:

- Development case
- Application
- Application stage
- Technical review
- Meeting
- Decision
- Approval
- Amendment cycle

Suggested document metadata:

```text
document_id
application_id
case_id
document_type
document_category
file_name
file_path
revision_number
submission_date
uploaded_by
uploaded_at
department_id
verification_status
document_status
remarks
```

Possible document types:

- Application form
- Land title
- Planning report
- Technical report
- Plan drawing
- Calculation report
- Approval letter
- Meeting paper
- Site photograph
- Correspondence
- Amendment document
- Supporting document

---

### 10.6 Fees and Payments

Each application should maintain its own fee and payment records.

Suggested structures:

```text
CORE_FEE_ASSESSMENT
CORE_PAYMENT
CORE_PAYMENT_ALLOCATION
```

Fee information may include:

- Fee type
- Assessment date
- Assessed amount
- Invoice number
- Due date
- Payment status
- Outstanding amount

Payment information may include:

- Transaction reference
- Payment date
- Amount
- Payment method
- Payment channel
- Receipt number
- Verification status
- Verified by
- Source system
- Refund information

The system should not store only one `PAID` or `NOT PAID` field because one application may contain multiple fees or transactions.

---

### 10.7 File Registration and Movement

Suggested structures:

```text
CORE_FILE
CORE_FILE_MOVEMENT
```

File information may include:

- File number
- Registration date
- File category
- Current location
- Current responsible department
- Current responsible officer
- Archive status

File movement history should include:

- From department
- To department
- From officer
- To officer
- Transfer date
- Received date
- Return date
- Movement status
- Remarks

The system must preserve movement history instead of overwriting the previous location.

---

### 10.8 Tasks and Assignments

An application may have multiple tasks or assignments.

Examples:

```text
Application
|
+-- Planning Assessment
+-- Engineering Assessment
+-- Building Assessment
+-- Landscape Assessment
+-- Payment Verification
+-- File Registration
```

Suggested task fields:

```text
task_id
application_id
department_id
assigned_officer
task_type
assigned_date
due_date
completed_date
task_status
priority
remarks
```

The system should support:

- Assignment
- Reassignment
- Due dates
- SLA monitoring
- Escalation
- Completion
- Cancellation
- Assignment history

---

### 10.9 Departmental Processing

One application may involve one or several departments.

Suggested structure:

```text
CORE_APPLICATION_DEPARTMENT
- application_department_id
- application_id
- department_id
- department_role
- assigned_officer
- process_status
- start_date
- due_date
- completed_date
- recommendation
- remarks
```

Department roles may include:

- Primary Department
- Joint Processing Department
- Technical Reviewing Department
- Supporting Department
- Approval Department
- Information Only

Example:

```text
Pelan Kerja Tanah
|
+-- Primary Department:
|   Jabatan Perancang Bandar
|
+-- Joint Processing Department:
    Jabatan Kejuruteraan
```

Each department must update its own process record without overwriting another department's assessment.

---

### 10.10 Technical Reviews

Suggested structures:

```text
CORE_TECHNICAL_REVIEW
CORE_TECHNICAL_REVIEW_COMMENT
CORE_TECHNICAL_CONDITION
```

Technical-review information may include:

- Reviewing department
- Reviewing officer
- Review type
- Request date
- Due date
- Response date
- Review status
- Recommendation
- Technical comments
- Conditions
- Attachments

An application may require:

- One technical review
- Several sequential reviews
- Several parallel reviews
- No technical review

---

### 10.11 Meeting and Decision

Suggested structures:

```text
CORE_MEETING
CORE_MEETING_CASE
CORE_DECISION
```

The system should support:

- Meeting scheduling
- Agenda sequence
- Case presentation
- Postponement
- Reconsideration
- Meeting minutes
- Decision recording
- Decision conditions
- Multiple meeting appearances

Possible decisions:

- Approved
- Approved With Conditions
- Rejected
- Postponed
- Returned for Amendment
- Withdrawn
- No Objection
- Further Review Required

Not every application must go through a meeting.

---

### 10.12 Approval Management

Suggested structures:

```text
CORE_APPROVAL
CORE_APPROVED_PLAN
CORE_APPROVAL_CONDITION
CORE_APPROVED_COMPONENT
```

Approval information may include:

- Approval reference number
- Approval date
- Approval category
- Approval type
- Approval status
- Approval validity date
- Approval verification date
- Approved-plan reference
- Technical conditions
- Approved development components

An application may have:

- Original approval
- Amended approval
- Extension
- Renewal
- Replacement approval
- Appeal result

---

### 10.13 Status and Audit History

Suggested structures:

```text
CORE_STAGE_HISTORY
CORE_STATUS_HISTORY
CORE_AUDIT_LOG
```

The system should record:

- Previous stage
- New stage
- Previous status
- New status
- Action performed
- Action date
- Officer
- Department
- Remarks
- Return reason
- Changed fields

Users should not directly overwrite stage or status values without a history record.

---

## 11. Department-Specific Modules

### 11.1 PKM Module

Possible PKM-specific information:

- Planning permission category
- Planning permission type
- Existing land use
- Proposed land use
- Development components
- Density
- Plot ratio
- Number of units
- Number of storeys
- Building height
- Planning standards
- Technical planning conditions
- Approval validity
- Approved land-use schedule

Primary department:

- Jabatan Perancang Bandar

Possible supporting departments:

- Jabatan Kejuruteraan
- Jabatan Bangunan
- Jabatan Landskap
- Other internal or external agencies

---

### 11.2 Pelan Bangunan Module

Possible building-plan information:

- Building category
- Building use
- Number of blocks
- Number of storeys
- Gross floor area
- Floor area by use
- Building height
- Setback
- Parking provision
- Accessibility requirements
- Building-plan reference
- Approved-plan number
- Technical conditions

Primary department:

- Jabatan Bangunan

The relationship with PKM should be optional and based on actual requirements.

---

### 11.3 Pelan Kerja Tanah Module

Possible earthwork information:

- Existing ground level
- Proposed platform level
- Cut volume
- Fill volume
- Earthwork area
- Retaining structure
- Slope information
- Erosion and sediment control
- Temporary drainage
- Disposal location
- Haulage route
- Earthwork phase
- Technical conditions

Responsible departments may include:

- Jabatan Perancang Bandar
- Jabatan Kejuruteraan

Example parallel process:

```text
PKT Application
|
+-- Planning Assessment
+-- Engineering Assessment
|
+-- Consolidated Result
```

The application should remain one record even when several departments are involved.

---

### 11.4 Pelan Kerja Parit Module

Possible drainage-plan information:

- Drainage-system type
- Catchment area
- Design discharge
- Existing drain
- Proposed drain
- Drain dimensions
- Drain reserve
- Detention facility
- Retention facility
- Outfall location
- Discharge point
- Hydraulic calculation
- Technical conditions

Primary department:

- Jabatan Kejuruteraan

The application may be standalone or linked to another development application.

---

### 11.5 Pelan Landskap Module

Possible landscape-plan information:

- Landscape category
- Open-space area
- Softscape area
- Hardscape area
- Existing trees
- Trees to be retained
- Proposed trees
- Plant species
- Recreational facilities
- Buffer planting
- Maintenance period
- Replacement-planting conditions
- Approved-plan reference

Primary department:

- Jabatan Landskap

The application may be standalone or linked to PKM, PB or another relevant application.

---

# Part IV — Workflow Engine

## 12. Configurable Workflow Engine

Each application type should use its own workflow template.

Suggested configuration tables:

```text
CONFIG_APPLICATION_TYPE
CONFIG_APPLICATION_SUBTYPE
CONFIG_WORKFLOW_TEMPLATE
CONFIG_WORKFLOW_STAGE
CONFIG_WORKFLOW_TRANSITION
CONFIG_DOCUMENT_REQUIREMENT
CONFIG_FORM_SECTION
CONFIG_FIELD_DEFINITION
CONFIG_FEE_RULE
CONFIG_DEPARTMENT_RULE
CONFIG_ROLE_PERMISSION
CONFIG_REFERENCE_NUMBER_RULE
```

A workflow should support:

- Mandatory stages
- Optional stages
- Conditional stages
- Parallel stages
- Return to previous stage
- Amendment cycles
- Reassessment
- Multiple departmental reviews
- Approval stages
- Rejection
- Withdrawal
- Cancellation
- SLA and escalation rules

---

## 13. Workflow Examples

### 13.1 PKM Example

```text
Application Registration
→ Completeness Check
→ Fee Assessment
→ Payment Verification
→ Technical Circulation
→ Planning Assessment
→ Committee Meeting
→ Decision
→ Approval Preparation
→ Approval Issued
→ Closed
```

A simplified PKM may skip one or more stages where permitted.

---

### 13.2 Pelan Bangunan Example

```text
Application Registration
→ Document and Drawing Validation
→ Fee Assessment
→ Building Technical Assessment
→ Amendment, if required
→ Amendment Verification
→ Approval
→ Plan Endorsement
→ Closed
```

---

### 13.3 Pelan Kerja Tanah Example

```text
Application Registration
→ Completeness Check
→ Parallel Departmental Assessment
    |
    +-- Planning Assessment
    |
    +-- Engineering Assessment
→ Amendment, if required
→ Consolidated Recommendation
→ Approval
→ Closed
```

---

### 13.4 Pelan Landskap Example

```text
Application Registration
→ Landscape Technical Review
→ Plan Review
→ Amendment, if required
→ Approval
→ Maintenance Requirement
→ Closed
```

---

## 14. Conditional Workflow Examples

### Technical Circulation

```text
Is technical circulation required?
|
+-- Yes
|   |
|   +-- Create technical review assignments
|
+-- No
    |
    +-- Continue to internal assessment
```

### Committee Meeting

```text
Does the application require a committee decision?
|
+-- Yes
|   |
|   +-- Schedule meeting
|
+-- No
    |
    +-- Proceed to departmental approval
```

### Joint Departmental Assessment

```text
Which departmental assessments are required?
|
+-- Planning only
|
+-- Engineering only
|
+-- Planning and Engineering in parallel
```

---

# Part V — Data, Architecture and Technology

## 15. Suggested Database Structure

### 15.1 Core Tables

```text
CORE_DEVELOPMENT_CASE
CORE_APPLICATION
CORE_APPLICATION_RELATION
CORE_APPLICATION_TYPE
CORE_DEPARTMENT
CORE_APPLICATION_DEPARTMENT
CORE_PARTY
CORE_APPLICATION_PARTY
CORE_DEVELOPMENT_CASE_PARTY
CORE_SITE
CORE_APPLICATION_SITE
CORE_LOT
CORE_APPLICATION_LOT
CORE_DOCUMENT
CORE_FEE_ASSESSMENT
CORE_PAYMENT
CORE_PAYMENT_ALLOCATION
CORE_FILE
CORE_FILE_MOVEMENT
CORE_TASK
CORE_ASSIGNMENT
CORE_TECHNICAL_REVIEW
CORE_TECHNICAL_REVIEW_COMMENT
CORE_TECHNICAL_CONDITION
CORE_MEETING
CORE_MEETING_CASE
CORE_DECISION
CORE_APPROVAL
CORE_APPROVED_PLAN
CORE_APPROVAL_CONDITION
CORE_APPROVED_COMPONENT
CORE_STAGE_HISTORY
CORE_STATUS_HISTORY
CORE_NOTIFICATION
CORE_AUDIT_LOG
```

### 15.2 Department Extension Tables

```text
APP_PKM
APP_PKM_COMPONENT
APP_PKM_LAND_USE
APP_PKM_CONDITION

APP_BUILDING_PLAN
APP_BUILDING_BLOCK
APP_BUILDING_FLOOR
APP_BUILDING_COMPONENT

APP_EARTHWORK_PLAN
APP_EARTHWORK_PHASE
APP_EARTHWORK_LEVEL
APP_EARTHWORK_CONDITION

APP_DRAINAGE_PLAN
APP_DRAINAGE_COMPONENT
APP_DRAINAGE_OUTFALL
APP_DRAINAGE_CONDITION

APP_LANDSCAPE_PLAN
APP_LANDSCAPE_COMPONENT
APP_LANDSCAPE_TREE
APP_LANDSCAPE_CONDITION
```

The extension-table approach prevents unrelated application fields from being placed in one large table.

AI and automation tables (for example `AI_DOCUMENT_CLASSIFICATION` and `AI_EXTRACTION_RESULT`) are described separately in Section 22.4, since they support the AI Services Layer rather than core department processing.

---

## 16. System Architecture

```text
                    MBSP Authentication
                             |
                             v
+------------------------------------------------------+
| iNDECS — Intergrated Development Control System      |
|                                                      |
| Core Services                                        |
| - Application registration                           |
| - Optional development case                          |
| - Application relationships                          |
| - Workflow and assignments                           |
| - Documents                                          |
| - Fees and payments                                  |
| - File movement                                      |
| - Technical reviews                                  |
| - Meetings and decisions                             |
| - Approvals                                          |
| - Audit and reporting                                |
+-----------------------------+------------------------+
                              |
          +-------------------+-------------------+
          |                   |                   |
          v                   v                   v
+----------------+   +------------------+   +------------------+
| PKM Module     |   | Building Module  |   | Earthwork Module |
+----------------+   +------------------+   +------------------+
          |                   |                   |
          +-------------------+-------------------+
                              |
                              v
                 +---------------------------+
                 | ArcGIS Enterprise         |
                 |                           |
                 | Application boundaries    |
                 | Cadastral lots            |
                 | Building footprints       |
                 | Earthwork areas           |
                 | Drainage alignments       |
                 | Landscape areas           |
                 | Proposed plans            |
                 | Approved plans            |
                 +---------------------------+
```

The diagram above covers core and spatial services. Section 22 describes the AI Services Layer that extends this architecture without sitting inside the core approval path.

---

## 17. Recommended Technology Approach

A hybrid architecture is recommended.

### Web Application

Use a web application framework such as Laravel for:

- Data-entry forms
- Workflow logic
- User roles
- Department permissions
- Task assignment
- File movement
- Payment records
- Technical reviews
- Meetings
- Decisions
- Approvals
- Reporting
- Integration APIs

### ArcGIS Enterprise

Use ArcGIS Enterprise for:

- Spatial data storage
- Feature services
- Geometry editing
- Map visualisation
- Spatial search
- Cadastral-lot selection
- Proposed-plan display
- Approved-plan display
- Spatial analysis

### Experience Builder

Use Experience Builder for:

- Map-oriented application search
- Application-site review
- Layer visibility
- Simple spatial editing
- Application-detail panels
- Opening departmental forms
- Viewing temporary georeferenced plans

Experience Builder should not be responsible for all business workflow logic.

---

# Part VI — Access, Security and Reference Data

## 18. User Roles and Permissions

Suggested roles:

- System Administrator
- Application Registrar
- Planning Officer
- Building Officer
- Engineering Officer
- Landscape Officer
- GIS Officer
- Finance Officer
- Records Officer
- Technical Reviewer
- Meeting Secretariat
- Approving Officer
- Management Viewer

Permissions should be controlled by:

- Platform access
- Department
- Application type
- Workflow stage
- Assigned application
- Departmental process
- Action permission
- Field permission
- Record ownership

Example rules:

- Planning officers can update planning assessments.
- Engineering officers can update engineering assessments.
- Building officers can update building-plan assessments.
- Landscape officers can update landscape assessments.
- Finance officers can update payment information but not GIS geometry.
- GIS officers can update site geometry but cannot approve applications.
- Users may edit only applications assigned to them or their department.
- Completed stages become read-only unless reopened by an authorised user.
- AI-generated suggestions are visible only to officers who already have permission to act on the related record, and accepting a suggestion is attributed to that officer (see Section 22.5).

---

## 19. Multiuser Editing and Data Protection

The system should use optimistic concurrency.

Suggested fields:

```text
row_version
updated_at
updated_by
```

When a user saves a record, the system should verify that the record has not been updated by another user.

Sensitive processes may also use temporary locks:

```text
locked_by
locked_at
lock_expires_at
```

Splitting application data into separate modules and departmental-process records will further reduce update conflicts.

---

## 20. Lookup and Reference Data

Common lookup values should be centrally managed.

Suggested reference tables:

```text
REF_APPLICATION_CATEGORY
REF_APPLICATION_TYPE
REF_APPROVAL_CATEGORY
REF_PLAN_TYPE
REF_LAND_USE_TYPE
REF_LAND_USE_CATEGORY
REF_LAND_USE_ACTIVITY
REF_DISTRICT
REF_MUKIM
REF_DUN
REF_PARLIAMENT
REF_DEPARTMENT
REF_STATUS
REF_STAGE
REF_DOCUMENT_TYPE
REF_DECISION_TYPE
```

Codes should not be independently maintained in multiple Excel files or hard-coded in each module.

---

## 21. Excel Migration and Integration

Existing Excel files should be treated as staging sources rather than permanent operational databases.

Suggested process:

```text
Excel Workbook
      |
      v
Staging Import
      |
      +-- Validate application number
      +-- Validate dates
      +-- Validate status
      +-- Detect duplicates
      +-- Match application and lot
      |
      v
iNDECS Production Tables
```

Suggested staging tables:

```text
STG_IMPORT_BATCH
STG_APPLICATION
STG_PAYMENT
STG_FILE_REGISTRATION
STG_FILE_MOVEMENT
STG_TECHNICAL_REVIEW
STG_IMPORT_ERROR
```

Each import should record:

- Batch ID
- Source file
- Source department
- Import date
- Imported by
- Total rows
- Successful rows
- Rejected rows
- Duplicate rows
- Validation errors

The long-term objective should be to replace manually maintained Excel files with system modules, reports, APIs or scheduled integration jobs.

---

# Part VII — Intelligent Automation

## 22. AI Integration Strategy

### 22.1 Guiding Principle

AI within iNDECS is an assistive layer, not a decision-making authority. AI features may classify, extract, suggest, predict, draft and summarise, but statutory decisions, technical conditions and approvals remain the responsibility of the authorised officer, department or committee. Every AI-generated output must be reviewable, editable and traceable to a human decision.

This principle applies to every capability described below and should be treated as a design constraint, not an implementation detail.

---

### 22.2 AI Capability Map

AI capabilities are grouped by the stage of the application lifecycle they support, using the same stages already defined in the workflow and module sections above.

**A. Intake and Registration**

| Capability | What it does | Related Section |
|---|---|---|
| Document classification | Suggests `document_type` and `document_category` for each uploaded file so officers do not sort documents manually. | 10.5 Document Management |
| Completeness checking | Compares uploaded documents against the configured requirement list for the selected application type and flags missing items before submission is accepted. | 12 Configurable Workflow Engine |
| Data extraction from submitted forms | Reads applicant, developer, lot and site details from application forms and land titles to pre-fill the application record, reducing repeated manual entry. | 8 Application Record, 10.3 Site and Cadastral Lots |
| Legacy record interpretation | Reads scanned or inconsistent Excel records during migration and proposes matches to application numbers and lots, for staff confirmation. | 21 Excel Migration and Integration |

**B. Technical Assessment**

| Capability | What it does | Related Section |
|---|---|---|
| Zoning and land-use pre-check | Compares declared density, plot ratio and land use against zoning rules and flags likely non-compliance before formal technical review begins. | 11.1 PKM Module |
| Spatial conflict detection | Cross-checks application geometry against other pending applications, restricted areas and flood-prone zones held in ArcGIS Enterprise. | 10.4 GIS Module |
| Plan-drawing data extraction | Reads submitted CAD or PDF drawings and proposes values for setback, gross floor area, storeys and drainage discharge, for officer verification rather than automatic acceptance. | 11.2–11.4 Department Modules |
| Technical-review summarisation | Produces short summaries of lengthy technical reports and comments for committee papers and officer handover. | 10.10 Technical Reviews |

**C. Workflow and Case Management**

| Capability | What it does | Related Section |
|---|---|---|
| Assignment support | Suggests an officer for a new task based on department, workload and case type; a supervisor confirms the assignment. | 10.8 Tasks and Assignments |
| SLA risk flagging | Identifies applications likely to breach SLA based on stage, age and historical patterns, so supervisors can intervene early. | 10.8 Tasks and Assignments |
| Natural-language case search | Lets officers query application history in plain language, for example applications in a given mukim awaiting engineering assessment. | 22.3 Suggested AI Services Layer |

**D. Applicant-Facing Services**

| Capability | What it does | Related Section |
|---|---|---|
| Requirement and status assistant | Answers applicant questions about required documents, fees and application status, and escalates to a human officer where needed. | 27 Future Enhancements |
| Pre-submission checklist assistant | Walks an applicant through the correct document checklist for their selected application type before submission. | 12 Configurable Workflow Engine |

**E. Management and Reporting**

| Capability | What it does | Related Section |
|---|---|---|
| Narrative dashboard summaries | Generates a plain-language summary of dashboard figures, for example monthly throughput and bottleneck departments. | 23.1 Main Dashboard |
| Anomaly flagging | Flags unusual patterns in payment, file-movement or status-change records for audit follow-up, without blocking the underlying transaction. | 10.6 Fees and Payments, 10.7 File Registration and Movement |

---

### 22.3 Suggested AI Services Layer

AI capabilities should be delivered through a distinct services layer rather than embedded directly inside department modules, so that models can be upgraded, monitored and audited independently of core business logic.

```text
                    iNDECS Core Services
                             |
                             v
                 +---------------------------+
                 | AI Services Layer          |
                 |                            |
                 | - Document Intelligence    |
                 |   (classification, OCR,    |
                 |   field extraction)        |
                 | - Language Services        |
                 |   (summarisation, chat,    |
                 |   natural-language search) |
                 | - Spatial Intelligence     |
                 |   (conflict and zoning     |
                 |   pre-checks, with ArcGIS) |
                 | - Prediction Services      |
                 |   (SLA risk, workload)     |
                 | - Model Governance         |
                 |   (versioning, monitoring, |
                 |   feedback capture)        |
                 +--------------+-------------+
                                |
                 +--------------+--------------+
                 |                             |
                 v                             v
        iNDECS Core Database          ArcGIS Enterprise
```

The AI Services Layer reads from the core database and ArcGIS Enterprise for context, but writes results only to review queues and suggestion fields — never directly to approved application data.

---

### 22.4 Suggested Data Model Additions

```text
AI_DOCUMENT_CLASSIFICATION
- classification_id
- document_id
- suggested_document_type
- confidence_score
- model_version
- reviewed_by
- review_status
- created_at

AI_EXTRACTION_RESULT
- extraction_id
- application_id
- document_id
- field_name
- extracted_value
- confidence_score
- model_version
- accepted_by
- accepted_at

AI_COMPLIANCE_FLAG
- flag_id
- application_id
- flag_type
- flag_description
- severity
- model_version
- status
- resolved_by
- resolved_at

AI_SLA_PREDICTION
- prediction_id
- application_id
- predicted_risk_level
- predicted_breach_date
- model_version
- generated_at

AI_CHAT_SESSION
AI_CHAT_MESSAGE
- session_id / message_id
- application_id (nullable)
- user_type
- message_text
- response_text
- created_at

AI_MODEL_VERSION
- model_version_id
- model_name
- version_label
- deployed_at
- deprecated_at
- notes

AI_FEEDBACK_LOG
- feedback_id
- reference_type
- reference_id
- officer_id
- feedback_type
- remarks
- created_at
```

Every AI table carries a `model_version` and, where relevant, a `confidence_score` and a human `reviewed_by` or `accepted_by` field. This keeps every AI-influenced value traceable to the model that produced it and the officer who confirmed it, consistent with the audit approach in Section 10.13.

---

### 22.5 Governance and Human Oversight

- AI outputs are always presented as suggestions with a visible confidence indicator, never as pre-filled final values.
- An officer's decision to accept, reject or correct an AI suggestion is logged in `AI_FEEDBACK_LOG` and reflected in `CORE_AUDIT_LOG`.
- No application may be auto-approved, auto-rejected or auto-closed by an AI process.
- Documents and personal data processed by AI services stay within approved, secure infrastructure consistent with Malaysia's Personal Data Protection Act 2010. Sending applicant personal data to a third-party AI service requires explicit data-handling review.
- Model accuracy is reviewed periodically against officer feedback captured in `AI_FEEDBACK_LOG`, with a defined process for retraining or rollback.
- Each production model is tracked in `AI_MODEL_VERSION` so that any historical AI-assisted suggestion can be traced to the exact model that generated it.

---

### 22.6 Phased AI Rollout

AI features depend on consistent, well-structured operational data, so they should follow the core platform and shared modules rather than launch alongside them.

```text
Early (once documents and forms are flowing through iNDECS):
- Document classification
- Completeness checking
- Data extraction from forms

Later (once case history has accumulated):
- SLA risk flagging
- Spatial conflict detection
- Natural-language search and reporting

Longer term:
- Applicant-facing virtual assistant
- Plan-drawing data extraction across all department modules
```

This sequencing is reflected in Phase 7 of Section 26, Suggested Implementation Phases.

---

# Part VIII — Interface, Validation and Delivery

## 23. Recommended User Interface

### 23.1 Main Dashboard

The main dashboard should display:

- My assigned applications
- New applications
- Applications awaiting action
- Applications due today
- Overdue applications
- Applications returned for amendment
- Applications awaiting technical feedback
- Applications awaiting decision
- Recently approved applications

Suggested filters:

- Application type
- Department
- Current status
- Current stage
- Submission year
- Assigned officer
- District
- Mukim
- Development case
- Related application

---

### 23.2 Application Workspace

Suggested tabs:

```text
Overview
Application Details
Applicant and Consultant
Site and Lots
Map
Department Form
Documents
Fees and Payments
File Movement
Technical Reviews
Assignments
Meeting
Decision
Approval
Related Applications
Timeline
Audit History
```

The `Department Form` should change according to the selected application type.

---

### 23.3 Development Case Workspace

Suggested tabs:

```text
Overview
Related Applications
Applicant and Developer
Site and Lots
Map
Shared Documents
Overall Development Status
Application Dependencies
Timeline
```

---

## 24. Example Scenarios

### Scenario 1: PKM Only

```text
Application:
PKM/2026/00125

Related Applications:
None
```

No PB, PKT, PKP or PL record is required.

---

### Scenario 2: PKM, PB and PKT

```text
Development Case:
Proposed Commercial Development

Applications:
- PKM/2026/00130
- PB/2026/00245
- PKT/2026/00062
```

Each application has its own workflow and status.

```text
PKM Status:
Approved

PB Status:
Technical Assessment

PKT Status:
Awaiting Amendment
```

---

### Scenario 3: Standalone PKT

```text
Application:
PKT/2026/00078

Related PKM:
None

Development Case:
None
```

The PKT application is processed independently.

---

### Scenario 4: PKM and Pelan Landskap

```text
Development Case:
Proposed Recreational Development

Applications:
- PKM/2026/00165
- PL/2026/00019
```

No PB or PKT record is required.

---

### Scenario 5: Applications Submitted at Different Times

```text
2026:
PKM submitted and approved

2027:
Pelan Bangunan submitted

2028:
Pelan Landskap submitted
```

The later applications may be linked to the same development case when registered.

---

### Scenario 6: Concurrent Applications

```text
Submission Date:
15 July 2026

Applications:
- PKM
- Pelan Kerja Tanah
- Pelan Kerja Parit
```

The applications may be processed concurrently, subject to configured dependency rules.

---

## 25. Non-Functional Requirements

The system should provide:

- Role-based access control
- Department-based data access
- Audit logging
- Multiuser support
- Concurrency protection
- Secure authentication
- Data validation
- Configurable workflows
- Scalable module design
- API integration capability
- GIS integration
- Document versioning
- Search and filtering
- Reporting and dashboards
- Backup and recovery
- Data-retention controls
- Mobile-responsive interface
- Performance suitable for daily operational use
- Personal data protection consistent with Malaysia's Personal Data Protection Act 2010, covering applicant, developer and consultant records
- AI output traceability, including mandatory human review before any AI-influenced value is accepted (see Section 22.5)

---

## 26. Suggested Implementation Phases

### Phase 1 — Discovery and Core Design

- Confirm all application types
- Document each department's workflow
- Identify mandatory and optional stages
- Identify current Excel sources
- Define common and application-specific fields
- Define user roles and permissions
- Confirm GIS feature classes
- Prepare conceptual and logical data models

---

### Phase 2 — Core Platform

Develop:

- Application-type configuration
- Standalone application registration
- Optional development case
- Application relationships
- Applicant and consultant records
- Site and cadastral lots
- GIS integration
- Document management
- Assignment
- Status history
- Audit log

Use PKM as the first implementation module.

---

### Phase 3 — Shared Operational Modules

Develop:

- Fees and payments
- File registration
- File movement
- Technical consultation
- Departmental processing
- Task and SLA monitoring
- Notifications
- Dashboard and reporting

---

### Phase 4 — Additional Application Modules

Add:

- Pelan Bangunan
- Pelan Kerja Tanah
- Pelan Kerja Parit
- Pelan Landskap

Each module should reuse the shared core but maintain its own fields and workflow.

---

### Phase 5 — Application Relationships and Dependencies

Develop:

- Related-application management
- Prerequisite rules
- Concurrent processing
- Development-case overview
- Cross-application status checks
- Shared applicant and site information
- Shared document reuse

---

### Phase 6 — Integration and Migration

Develop:

- Excel staging and migration
- Payment-system integration
- File-registry integration
- OSC integration
- Meeting-system integration
- Approval-letter generation
- Management dashboards
- Historical-data migration

---

### Phase 7 — AI and Intelligent Automation

Develop:

- Document classification and completeness checking
- Data extraction from application forms and land titles
- Zoning and compliance pre-checks
- Spatial conflict detection
- SLA risk flagging
- Natural-language search and reporting
- Applicant-facing virtual assistant

AI features should be introduced only once Phase 2 (Core Platform) and Phase 3 (Shared Operational Modules) are stable, since model quality depends on consistent, well-structured operational data. Document classification and completeness checking may begin as soon as a meaningful volume of historical documents exists; predictive features such as SLA risk flagging and spatial conflict detection depend on accumulated case history and should follow. See Section 22.6 for the full sequencing.

---

## 27. Future Enhancements

Possible future enhancements include:

- Applicant self-service portal
- Online submission
- Digital signatures
- Automated fee calculation
- Mobile site inspection
- QR-based file tracking
- Public application-status enquiry
- Email and messaging notifications
- Cross-department analytics
- Integration with Smart City platforms

AI-related enhancements — document classification, plan-data extraction, spatial conflict checking, zoning compliance pre-checks and more — are covered in full in Section 22, AI Integration Strategy.

---

## 28. Final System Vision

iNDECS should be designed as:

> A configurable, GIS-enabled development-control platform that supports standalone applications, related applications and any valid combination of technical applications without forcing every development through the same process — supported by an AI-assisted layer that speeds up officers' work without replacing their judgement.

Final conceptual structure:

```text
iNDECS
|
+-- Standalone Applications
|      |
|      +-- PKM
|      +-- Pelan Bangunan
|      +-- Pelan Kerja Tanah
|      +-- Pelan Kerja Parit
|      +-- Pelan Landskap
|
+-- Optional Development Cases
|      |
|      +-- One related application
|      +-- Multiple related applications
|      +-- Applications submitted concurrently
|      +-- Applications submitted at different times
|
+-- Shared Core Services
|      |
|      +-- GIS
|      +-- Applicant and consultant
|      +-- Site and cadastral lots
|      +-- Documents
|      +-- Payments
|      +-- File movement
|      +-- Workflow
|      +-- Technical reviews
|      +-- Assignments
|      +-- Meetings
|      +-- Decisions
|      +-- Approvals
|      +-- Audit and reporting
|
+-- AI Services (advisory only)
       |
       +-- Document intelligence
       +-- Language services
       +-- Spatial intelligence
       +-- Prediction services
       +-- Model governance
```

## 29. Key Principles

1. Every application can exist independently.
2. A development case is optional.
3. Related applications can be linked when necessary.
4. No application type is universally mandatory.
5. No fixed sequence is imposed on all developments.
6. Each application type has its own configurable workflow.
7. One application may involve one or several departments.
8. Departmental assessments are stored separately.
9. Shared information can be reused without forcing identical data.
10. Each application may maintain its own site, lots and geometry.
11. GIS and business data remain integrated.
12. Existing Excel data is migrated through controlled staging.
13. New application types can be added without redesigning the entire platform.
14. All status changes, assignments and decisions are auditable.
15. The system is designed for multiuser, multi-department operation.
16. AI provides suggestions, extraction and automation support; officers and committees retain sole authority over decisions and approvals.
