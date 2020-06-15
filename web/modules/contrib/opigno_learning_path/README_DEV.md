# Opigno Learning Path developer documentation


## How to add a new Learning Path content type (using plugin system)

The Learning Path module comes with a new Plugin type named **LearningPathContentType**.
To fully understand the plugin system, I would recommend you to read and follow
[this tutorial](https://drupalize.me/blog/201409/unravelling-drupal-8-plugin-system).

First of all, a Learning Path Content Type should have at least:
- A title
- An image
- A "start" URL (like the "take" URL for a quiz)
- A creation and edition form
- A finish page where the button "next" or "finish" can appear.

To create a new Learning Path content type, you need to create a file in the folder
"src/Plugin/LearningPathContentType/" and name it as you want.
Inside this file, you will need to create a class extending `Drupal\opigno_learning_path\ContentTypeBase`.
Doing so, you will need to implements the needed methods. Let your IDE import the methods for you. Then complete the
methods according to their phpdoc imported from ContentTypeBase.
Each method is documented in his header comment in the *ContentTypeBase.php* file. Watch carefully what the method should
return according to the documentation of the method.

Then, you need to create a new comment header for you class following this format:
```php
/**
 * Class YourClassName // <- Change this
 * @package Drupal\your_module_machine_name // <- Change this
 *
 * @LearningPathContentType(
 *   id =             "YourPluginImplementationId", // <- Change this
 *   readable_name =  "The short name of your content type", // <- Change this
 *   description =    "This would be a longer description of your content type", // <- Change this
 *   entity_type =    "node" // <- Change this. Can be node, group, term, any_custom_entity, etc
 * )
 */
class ... extends ContentTypeBase
{}
```

Once all is done, refresh the caches, go to a Learning Path manager and try to add the content inside the Learning Path.

For example purpose, check the file *ContentTypeCourseExample.php* from the module opigno_course_content_example
(don't be disturbed by this file name... it has been created before the idea of a learning path. It should have been a
course content before a learning path content).


## How does the plugin works

A plugin works by using a Manager. A Manager will find all the plugin implementation and give to you what's in the
class header and can instantiate a complete content type object.

A Manager is a service. In our case, the manager is registered under the service "opigno_learning_path.content_types.manager".
So you can call the manager by doing `$manager = \Drupal::getContainer()->get('opigno_learning_path.content_types.manager');`.
Then, with the manager, you can find all the definitions of the content types and instantiate the one you need.

### To get all the plugin IDs
This is the basic to get the plugin IDs:
```php
$manager = \Drupal::getContainer()->get('opigno_learning_path.content_types.manager');
$definitions = $manager->getDefinitions();
```
The variable `$definitions` will contains the plugins in this format:
```text
Array
(
    [ContentTypeCourseExample] => Array
    (
        [id] => ContentTypeCourseExample
        [readable_name] => Course Example
        [description] => It's an example of a course content
        [entity_type] => node
        [class] => Drupal\opigno_course_content_example\Plugin\LearningPathContentType\ContentTypeCourseExample
        [provider] => opigno_course_content_example
    )
)
```

### Instantiate a content type
Once you found your content type ID (or plugin ID, it's the same), instantiate it doing like that:
```php
$content_type = $manager->createInstance($plugin_id);
```

And here you can access to all the methods from your implementation of the class *ContentTypeBase*.

### A word about its implementation
Here are the essentials files:

- *src/Annotation/LearningPathContentType.php*  
This file contains the variables that must be inside the class header of each plugin implementations.

- *src/ContentTypeInterface.php*  
This file contains the methods that each learning path content type classes must implement ! A very good thing to do is
to already read each method's header to understand more the process.

- *src/ContentTypeBase.php*  
This file contains an abstract class that each learning path content type must extend from. It has already the basic 
methods for accessing the class header information (id, entity_type, etc) and few other simple methods that each
implementation can override.

- *src/LearningPathContent.php*  
This file contains the basic object representing one learning path content. It's used in some methods from *ContentTypeBase*.
Do not confound with the class *LPManagedContent* that represents a row in the database for a learning path content.
Maybe a mix of both is possible... But I have no time to think about it now.

- *src/LearningPathContentTypesManager.php*  
Contains the manager (the service). Very simple to understand on its own.


## How does the Learning Path follow the steps (context)
The role of the class `LearningPathContext` is to save and destroy information about the current visiting learning path
or learning path content. It's saved in session.

In the method `LearningPathStepsController::start()`, the learning path content of the first step is saved in session
(using `LearningPathContext`). Then, on each request, we verify that the learning path content is accessible through the
global `$request` variable (using the Learning Path Content Types plugin). If it's still accessible, we keep the context.
If it's no more accessible, we destroy the context.
This verification is made in the method `LearningPathEventSubscriber::onKernelRequest()` that is subscribed to 
the event *KernelEvents::REQUEST*.

This context is essential to show the buttons "next" or "finish" to go to the next step or to finish the learning path.
Those buttons are conditionally declared in the file *Plugin/Derivative/StepsActions.php* and this derivative is
declared in the file *opigno_learning_path.links.action.yml*.
 
Here an example of the process:
1. Go to the Learning Path.  
Do nothing.

2. Start the learning path.  
Get the first step content and save the content ID in session.

3. Do the content (quiz, course, other...).  
If the content is still in accessible in `$request`, the content ID stays in session.
On each request, if there is still a context, check if the "next" or "finish" button can be shown.
This is done by refreshing the menu cache inside the class `LearningPathContext`.

4. (option A) Go to the front page.  
The content is not accessible through the global `$request` anymore. Destroy the context from session.
No more context, no more checks for a "next" or "finish" button to show.

4. (option B) Finish the content.  
If the content is finished, show the "next" or "finish" button. After finish every steps, go to "my results" page.


## How the success is calculated
The success is calculated in the method `LearningPathValidator::userHasPassed()`.

Pretty simple, it checks all the mandatory steps of the Learning Path. If one user score of a mandatory step is not
good enough, it return FALSE. Else, it returns TRUE.

So if there is no mandatory step, the method return TRUE directly.

In the method `LearningPathStepsController::finish()`, the result is calculated and saved in database using the entity
**LPResult**.


## How to add a group in a group (ggroup patch)
With the **group** module, you are able to link every contents to a group. But NOT a group to another group and it's
needed for linking a class to a learning path.

To do that, I used [this patch](https://www.drupal.org/node/2736233) that will be the official module release with group
named ggroup.

It's not finished yet (29.12.2017) but it will be soon enough.


## What's inside the config folder
In the config folder, you can find the YAML files used for the installation of the module.
It creates the Learning Path group type and creates the separate roles and permissions for this group type.

### Export the new config files
If you make any changes in the config of the site (changes of permission, new field in learning path, etc), use this
command in a linux shell to get the new config files.
You will first need to install the *Drupal* command line. Here is [a tutorial](https://drupalconsole.com/articles/how-to-install-drupal-console).

First, try to get all the config files you need by doing like this:
```
drupal debug:config | grep THE_SEARCHED_WORD
```
Replace THE_SEARCHED_WORD by a word specific to your needs. For example, to export the config files of a learning path,
you can search for the word "learning_path".

Once you find the good word to use, do this command:
```
drupal debug:config | grep THE_SEARCHED_WORD | while read line ; do drupal ces --module MY_MODULE_NAME --remove-uuid --remove-config-hash --name $line ; done
```
Replace THE_SEARCHED_WORD by the one you found before. Replace MY_MODULE_NAME by the module where you want to add the
config files. For example, "opigno_learning_path".

Doing so, it will export the config files found to a .yml file and put it in the config folder of the module specified.
Generally, there will be some extra files not needed. Simply remove them and everything will be okay !
