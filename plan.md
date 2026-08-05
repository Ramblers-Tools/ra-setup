# Incremental implementation plan for com_ra_setup

## Goal

Deliver the installation wizard in small, testable increments while preserving the ability to iterate on the later, more complex setup steps.

## Phase 1 - Bootstrap the component shell

### Deliverables
- Create the Joomla component manifest and basic component structure.
- Add front-end and admin entry points.
- Create the default controller/view wiring for the wizard.

### Acceptance criteria
- The component installs cleanly.
- The default view can be invoked from the front end.
- The admin wizard view is available to a super user.

## Phase 2 - Implement the wizard framework

### Deliverables
- Introduce the multi-step flow model.
- Add navigation between steps with Next/Previous controls.
- Persist progress using Joomla user state where appropriate.

### Acceptance criteria
- Users can move through the wizard in sequence.
- Each step renders independently.
- The current step can be determined reliably.

## Phase 3 - Implement Step 1: System setup

### Deliverables
- Area/group selection.
- Site name and strapline prompts.
- Validation and saving logic.
- Configuration updates through ToolsHelper.

### Acceptance criteria
- Valid codes are accepted.
- Invalid values produce clear messages.
- The required configuration values are written correctly.

## Phase 4 - Implement Step 2: Home page setup

### Deliverables
- Home page title and description prompts.
- Article creation/update for the home page.
- Website URL configuration update.

### Acceptance criteria
- The home article is created or updated.
- The content and title are saved correctly.
- The site URL is stored in the appropriate configuration field.

## Phase 5 - Implement Step 3 and Step 4

### Deliverables
- Walk details configuration.
- Optional component selection.
- Enable/disable logic for the selected components.

### Acceptance criteria
- The selected walk configuration is saved.
- The relevant components are enabled or disabled as requested.
- Validation prevents invalid setup combinations.

## Phase 6 - Implement Step 5 and Step 6: Committee details

### Deliverables
- Committee short-name input.
- Committee long-name and email collection.
- User/contact/profile creation logic.
- Role and group assignment logic.

### Acceptance criteria
- Committee data can be collected across steps.
- The required users, profiles, and contacts are created.
- The appropriate role and permission assignments are applied.

## Phase 7 - Implement Step 7: Email configuration

### Deliverables
- Domain and delivery-notification email prompts.
- Stub integration with SMTP2GO or the existing SMTP helper layer.
- Configuration storage for RA Delivery.

### Acceptance criteria
- The required values are collected and saved.
- The integration point is ready for real API calls later.

## Phase 8 - Final completion and admin wizard

### Deliverables
- Mark the setup as completed in #__ra_control.
- Ensure a single featured home article exists.
- Add the admin wizard view that can reopen any setup step.

### Acceptance criteria
- The component completes the full setup workflow.
- The admin view lists each step and links to the relevant front-end view.
- A completion message is displayed to the user.

## Implementation principles

- Keep each phase small and verifiable.
- Reuse existing Ramblers helper code wherever possible.
- Add validation and clear user feedback at each step.
- Prefer working end-to-end on one step before moving to the next.
