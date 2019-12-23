Mongodb Helper for Laravel
================

Artisan command to quickly check, delete, export mongo collections, and to import data.

<br>

* [Installation](#Installation)
* [Available Options](#Options-Available)
* [Basic Usage](#Basic-Usage)
* [Config](#Config)
* [Exports](#Exports)
* [Imports](#Imports)

###Options Available
| Option | Value | Description |
| --- | --- | --- |
| connection | string | Use a specific connection name instead of the default
| list |  | Output all existing collections |
| count | | Output the total of records in the specified collection |
| count_all | | Shows a table with all existing collections and their totals |
| limit | int | When using some data retrieval method, limit the results returned |
| delete | | Delete the entire content of the collection |
| drop | | Completely drop the collection |
| select | array | Retrieve only specific columns |
| download [**](#exports) | | Export the results into a json file |
| download_path [**](#exports) | string | Download the file into a specific directory |
| import_data | string | Import into a collection data exported as json
| dump | | Dump the results as they are |

<br>

## Installation
 
**`composer require deerdama/laravel-mongo-helper`**

<br>
  
  :exclamation: If you'll need to change the default config then you'll need to publish it:

`php artisan vendor:publish --provider=Deerdama\\MongoHelper\\MongoHelperServiceProvider`

-------------------
<br>

##Config

* After publishing the package you can edit the config in 
