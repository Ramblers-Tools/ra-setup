# com_ra_setup

## Purpose

com_ra_setup is a Joomla component that provides an installation wizard for creating or updating a RamblersWebs site from a template configuration.

The component is intended to guide a site owner or administrator through a sequence of setup steps that configure:

- the site identity and home-group context,
- the home page content,
- walk programme defaults,
- optional components,
- committee member data,
- mail and email configuration.

## Scope

The component will provide:

- a front-end wizard flow with step-by-step views,
- an administrative wizard view to re-run individual setup steps,
- integration with existing Ramblers tools and helper classes such as ToolsHelper,
- persistence of setup state through Joomla configuration, user state, and the existing database tables.

## Proposed user journey

1. The blank site loads with a featured article and a button that launches the wizard.
2. The wizard runs in create mode by default and proceeds step by step.
3. Each step validates input, stores interim data where needed, and advances to the next step.
4. On completion, the component records that setup has been completed in the database.

## High-level architecture

- Front-end views for the wizard steps.
- A single admin view for re-running steps.
- Shared helper logic in site/src/Helper/SetupHelper.php.
- Use of ToolsHelper functions for database access and configuration updates.
- Use of Joomla user state for temporary data across multi-step workflows.

## Planned component structure

- administrator/
  - views/wizard/
  - forms and XML definitions as required
- site/
  - src/Controller/
  - src/Helper/
  - src/Model/
  - src/View/
  - tmpl/

## Notes
