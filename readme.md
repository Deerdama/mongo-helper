Mongodb Helper for Laravel
================

Artisan command to quickly check, delete, export mongo collections, and to import data.

Install package: **`composer require deerdama/laravel-mongo-helper`**.

Should work on any laravel above `5.0`, however I can personally confirm only `5.8` and `6.x`. Feel free to let me know if you find out issues on other versions and I'll update the info..

<br>

* [Available Parameters](#Available-Parameters)
* [Config](#Config)
* [Basic Usage](#Basic-Usage)
* [Using *where* Conditions](#Using-WHERE-conditions)
* [Downloads](#Download-Collections)
* [Imports](#Import-Data)

<br>

## Available Parameters

* The first and only argument (optional) of the command is the collection name. The rest are all options

| Option | Value | Description |
| --- | --- | --- |
| connection | string | Use a specific connection name instead of the default |
| list |  | Output all existing collections |
| count | | Output the total of records found in the specified collection |
| count_all | | Shows a table with all existing collections and their totals |
| limit | int | When using some data retrieval method, limit the amount of results returned |
| delete | | Delete the entire content or the matching results from the collection |
| drop | | Completely drop the collection |
| select | array | Retrieve only specific columns |
| where [**](#Using-WHERE-conditions) | string | Where parameters |
| download [**](#Download-Collections) | | Export the results into a file |
| csv [**](#Download-Collections) | | Adding the option will export the data as csv (default is json) |
| download_path [**](#Download-Collections) | string | Download the file into a specific directory (will ignore the default config `directory`)  |
| import [**](#Import-Data) | string | Import into a collection data exported as json or csv|
| dump | | Simply `dump()` the results as they are |

----------------
<br>

## Config

  :exclamation: If you'll need to change the default config then you'll need to publish it first:
`php artisan vendor:publish --provider=Deerdama\\MongoHelper\\MongoHelperServiceProvider`

After publishing the package you can edit the config in `config/mongo_helper.php`. All parameters can be changed

* **`connection`**: default name of the connection is `mongodb`. You can change that in the config. 
Plus any specific connection name can be passed every time you are using the command, by adding the `connection` option (eg: `--connection=mongo_2`)

    <sup>_Connection = the name of your connection as it is in `database.php` The database connection driver has to be mongodb... Duh!!_</sup>

* **`storage`**: The default filesystem disk for both imports and exports is the `local`. Uses the storage facade => `Storage::disk('local')`

    <sup>_`Local` disk = laravel's default path for local storage is `storage/app/` depending on what you have in `filesystems.php`_</sup>
    

* **`directory`**: By default the downloads will go be in their own directory `mongodb/`

-----------------
<br>

## Basic Usage

Simply run the artisan command `db:mongo-helper` and add the correct option based on what you need, some options require a value, details about all of them can be found in the [Available Parameters](#Available-Parameters) table.

All fatal options (delete, drop...) will ask for an extra confirmation before being executed.

Couple of simple examples

* **`php artisan db:mongo-helper --count_all`** - will output a simple list of all your existing collections and the amount of records in each one of them
    <p>
      <img src="https://images2.imgbox.com/eb/e1/gpshlPRV_o.png">
    </p>
<br>

* **`php artisan db:mongo-helper test_collection --dump --limit=3 --select={name,location,skill}`** - will grab 3 items from `test_collection`, will select only the specified fields.. and will simply dump the results 
    <p>
      <img src="https://images2.imgbox.com/0e/44/e1mVJKx2_o.png" width="400px">
    </p>

---------------------
<br>
    
    
## Using `WHERE` conditions

**`php artisan db:mongo-helper test_collection --where="name, IN, [xyz,abc]" --where="id, BETWEEN, [5,99]" --where="deleted_at, NULL"`**


* Multiple `WHERE`s can be passed to the command, however each condition needs to be passed as a separate option

* Each `WHERE` needs to be passed as a string (inside quotes), containing the **column**, **operator** and **value** (separated by a comma), eg. **`--where="some_column, <>, some_value"`**. (Value not necessary for `NULL` and `NOT NULL`)

* All normal operators are accepted: `=`, `<>`, `>`, `<`, `IN`, `NOT IN`, `NULL`, `NOT NULL`, `BETWEEN`...

* **Arrays**: to pass a value as array for operators like `IN` or `BETWEEN`, just wrap the value inside square brackets, eg: **`--where="some_column, NOT IN, [aaa,bbb,ccc]"`**

* **Casting** :since mongo is sensitive to the content type, and by default the value parsed from the passed condition will be a `string`, you can cast the `value` to some specific type by adding **`cast=??`** as last parameter of the `--where` condition. 
For example if the collection had a column named `age` and the values were stored as `integer` then passing just `--where="age, >=, 18"` wouldn't return any result since the `18` would be considered a string. 
But passing **`--where="age, >=, 33, cast=int"`** will make sure that the value is considered as integer.

    Passing the value as array (inside `[]`) and also adding a specific `cast=??`, will apply the specified type to each item separately, eg: `--where="age, NOT IN, [15,20,100], cast=int"` (each *age* inside the array will be an integer)



--------------
<br>
    
## Download Collections

* By default the collection will be downloaded as **`json`**. The **`--csv`** option is available, you need the league package to use csv format `composer require league/csv`!!!! eg:
    <p>
     <img src="https://images2.imgbox.com/34/b3/NhyqrV2A_o.png">
    </p>


* By [default](#Config) the downloads will be in `mongodb/` directory. You can pass a specific path for the current download, eg:
    <p>
      <img src="https://images2.imgbox.com/5b/e7/mdBo7enc_o.png">
    </p>
    
* Tip.. if you need to download a collection regularly you can just add it to the `Console/Kernel.php` scheduler, eg: 

```php
    $schedule->command(MongoHelper::class, [
        'collection' => 'collection_name',
        '--download' => true
    ])->dailyAt('01:00');
```

-----------------
<br>

## Import Data

* You can import data into a specific collection from a `json` or `csv` file. Eg:

    `php artisan db:mongo-helper test_collection --import=test_collection_2020_01_01_03_48_51.json`

* If it doesn't find the file in the full path specified then it will try to find it in the [default directory](#Config)