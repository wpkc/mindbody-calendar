mindbody-calendar
=======

NOTICE: MindBody Online API access is only available to customers with Accelerate or Ultimate level accounts.  This code will NOT work with anything less.

Use this repository to add the following features to your custom WordPress theme:
* A sidebar widget that displays up to 12 upcoming events from your MindBody Online account.
* A calendar page template with AJAX navigation.
* A single event template to display event details when a user clicks on an event in sidebar widget or calendar.
* Uses WordPress transients to speed up response time and reduce the number of calls to MindBody Online.


Note: This is a fully functional, but unfinished project. I have no plans to continue work on it at this time. It was designed to replace the functionality of Modern Tribe's "The Events Calendar" WordPress plugin for a client that was using that before they signed up with MindBody Online.  I did not find out until after I wrote all this code that you must have an Accelerate or Ultimate level account to get API access, and my client only had a Solo account.  Upgrading their account was not possibile with their budget, so I discontinued development.


Installation:

* This is NOT written as a WordPress plug-in.  It was written for theme developers to add to their theme.
* Search and replace all occurances of the following text with your theme's text domain handle (if you have one):   /* , 'Your Text Domain Here' */
* Copy all .php, .css, and .js files to your theme folder.
* Add the following line of text to your theme's functions.php file: include_once 'functions-mindbody.php';
* Optional: Modify style-mindbody.css as needed.  CSS includes media queries to make the calendar fit on small/mobile screens.  Modify these as needed.
* Required: Display the MindBody Online logo on any web pages that display data obtained by use of the MindBody Online API.

Uninstall:

* Remove the following line of text from your WordPress theme's functions.php file: include_once 'functions-mindbody.php';
* Optional: remove all .php, .css, and .js files from this repository from your WordPress theme's folder.

Configuring:

* This code adds a Mindbody Events widget to your theme.  Add one copy of the widget to any sidebar on your site.  Configure your Mindbody API SourceName, Password, and SiteIDs in the widget and save the settings.  If you don't want to display the widget on your site, no problem, delete the widget from your sidebar after you have saved the Mindbody API credentials.  The widget saves a copy of these credentials in the WordPress options table for access by other parts of the code.
* You can set the widget Title and the maximum number of events to display in the sidebar.
* To display the events calendar, add a new Page to your WordPress web site. Set the page Template to "Mindbody Calendar." Give your page a title and save the page.  That's it.

Development Ideas:
* Converting this to a plug-in may make it easier to deploy.  
* Adding a Settings page instead of using the widget settings interface to set the API credentials may make it easier to use.
* Add more settings such as Transient caching timeouts for the widget, calendar and individual event data.
* Add hover effects to calendar events.
* Modify media queries section of style-mindbody.css to convert calendar table display into unordered list display on small screens to increase readability.
* Add calendar display mode switch to switch between table display and list display for large displays.
* You might want to add "the loop" to the calendar page template if you want to add additional content to the calendar page.
* Convert the calendar code into a WordPress shortcode so you can embed the calendar anywhere in any page without needing to use the calendar page template.