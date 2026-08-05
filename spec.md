## Preamble

A template site will be developed and configured with all the components installed, the required usergroups and permissions, and as many sensible defaults as possible. Multiple Walks Menus will be created on the assumption that only a sub-set will actually be required and the remainder will be unpublished.

A new component ra-setup (com_ra_setup) will be created as an Installation Wizard.

If a group wants to start a new website, RamblersWebs will take this template, register the required new domain and set up a blank website using this template.

On first run of the system, the installation wizard will be invoked by the following mechanism. The blank website will have a single featured Article with some introductory text and a button marked installation wizard. If they click the button, it will invoke com_ra_setup using the default view.
Installation wizard

 -  Initially, the wizard will be run in create mode. It will run through the various steps one at a time. Each step will have a button marked next to take them on to the next logical step. 
 -   On completion, it will record the time stamp in the database. It will use table #__ra_control and create or update record type 3.
 -   If it is being run in update mode, this will be recognisable by the fact that there is already a value in this field. The required behaviour may be slightly different.
 -   In create mode, the user need not be logged on, but it is only valid in update mode if the user is logged on as a Superuser.

## Architecture

One or more views will be present for the front end, with a single view in the admin application. The views will be called one, two etc in line with the steps described below. The steps will be captioned as follows:

 -   System setup
 -   Home page
 -   Walks programmes 
 -   Optional components
 -   Committee members - names
 -   Committee members - roles
 -   Email configuration

A single view is required for the Admin application that will enable a super user to rerun any of the individual steps from the front end. The view is to be called wizard. It will be based on the view reports from com_ra_events, which is included on the work in the workspace. It will need neither a model nor a table.  It will use the same formatting as in reports and will offer 7 options in a menu tile, using the captions specified above. In each case, a link will be provided to invoke the appropriate front end view.

 All database read operations will use the standard functions provided by com_ra_tools/ ToolsHelper: getValue, getItem and getRows. Where appropriate, many other functions in ToolsHelper are available, such as updateComponentParameters, and should be used. Common functions within this component should be created in site/src/Helper/SetupHelper.php

# Step 1 System setup

  -  Prompt for Area or Group.
   - Use "showon" to prompt for either a two character Area code or a four character Group code.
   - Prompt for site name in the header module 
   - Prompt for strapline in the header module 

The site name is held in 2 separate places. First, in the system configuration and secondly, in module RA header. This step retrieves the value held in configuration.php and prompts the user for a value to store as a parameter for RA Header, defaulting to the site value.

Validation:
    The code must be present on #__ra_groups or #__ra_areas

Saving:

The code must be saved in the configuration for RA Tools in field home_group.
Lookup the five nearest groups/areas using toolsHelper->getNearestOrganisations, store the codes that are returned as a comma delimited string in the configuration for RA Tools in field group_list.

The value given for the strapping is stored in the parameter for RA Header, overwriting any existing value. The field may be blank.

# Step 2 Home page

    - Prompt for the page title for the Home page 
    - Also prompt for a textual description for the site, as an Editor field.
    - A link to Facenpok may or may mot br required: If it is, prompt for the URL

Validation:

    Both text fields must be present

Saving:
The page title should be saved as the Title for the article havin.the alias  "home", overwriting any existing content.

The text must be saved in the body of the Article . 
Additionally, the URL of the website root is saved in the configuration for RA Tools, in a field called website.

# Step 3 Walk details

   - Display the Area/Group code from previous step and the associated name (use ToolsHelper / lookupGroup)
    -Display details of the neighbouring groups, using ToolsHelper / getNearestOrganisations:
    Ranking/Code/name/distance in miles
    -Prompt whether or not to show walks only for the home site, or to include other Groups.

If including other groups, select between:

    -Select by distance
    -Select from neighbouring groups

If distance is selected, prompt for radius in miles (default 40)

If neighbouring groups selected, choose a number between 1 and 5 from a drop-down list (default 5)

Validation:
If radius is selected, it must be a value between 10 and 100
Saving:
If necessary, update the component configuration with the appropriate number of group codes, held as a comma delimited string.
Depending on the selection, one or more menu entries will be unpublished (eg all entries where note='radius')

# Step 4 Select optional components

Prompt if the optional components are required:

   - RA Events
   - RA Mailman
   - RA Members
   - RA Delivery
    - RA SSO

Validation:

If RA Mailman is selected, RA Delivery must be selected.

Saving:
Enable/disable the components as appropriate

# Step 5 committee short names
Prompt for names: if any have already been stored in the database, these are used a default values (in update mode). Then, if any have been stored in the User State, these would take precedence. Since some names may be entered more than once, surnames are optional here, as they can be provided in the next step.

Fields required:

-    Chair
-    Webmaster
-    Membership Secretary
-    Secretary
-    Treasurer
-    Walks coordinator
-    List of other committee members
 -   If MailMan is installed, a list of members who can send mail shots
 -   If Events is installed, a list of members who can create events

Validation:

- The first two fields must have a single name each
- The next three fields may have a single name each
- For the three lists, multiple names can he entered, comma delimited.

Saving:
Nothing can yet be persisted to the database so all the data submitted must be saved in the User State to keep it available for the next step

# Step 6 Committee long names and emails
The names from the previous step are retrieved from the User State, copied into a common array without duplicates, then sorted.

For each name in turn:

  -  show as an input field so a Surname can be added if necessary
  -  show an input field to enter an email address,
  -  Display a literal detailing the role or roles they are filling eg Committee, or Chair+Mailman+Events

A "Previous" button is required to return to the previous step.

Saving:

   - For every individual, a record must be created in table #__users, and in #__ra_profiles.
   -  Webmaster will be made a Superuser
   - Chair added to groups com_ra_tools, com_ra_events, com_ra_mailman and com_ra_members
   - Chair and Membership Secretary added to groups com_ra_tools, and com_ra_members
   - All mailing lists updated with Chair as Owner
   - Webmaster and anyone authorised to send mailshots granted Authorship rights to all mailing lists
   - Event creators added to groups com_ra_tools and com_ra_events
   - Chair and Webmaster subscribed to list RT Users
    - For everbody, a Contact must be created:
        1. Name as given
        2. linked user from their user record,
        3. Role if available
        4. Category=Committee

# Step 7 Email configuration

Prompt for a 12 character "domain" name, defaulting to the first 12 chars of the Group name.

Prompt for the email address to be notified if emails cannot be delivered. Default to that of the Membership Secretary 

Saving:

Create a new domain in SMTP2GO, using their API. Initially, by creating and invoking a stub entry in SmtpHelper.php

Store the domain name and the email address in the component configuration for RA Delivery 

# Final save
- Update or create a record in table #__ra_control with record_type=3 and value the current timestamp
- Ensure there is only a single featured Article, and it is called Home
- Enqueue a message "Configuration completed" and returned to the home page.
