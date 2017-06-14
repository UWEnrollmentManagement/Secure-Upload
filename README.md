[![Build Status](https://travis-ci.org/UWEnrollmentManagement/secure-upload.svg?branch=master)](https://travis-ci.org/UWEnrollmentManagement/secure-upload)

# Secure-Upload

This library is intended to help protect the contents of uploaded documents against an attacker who might gain file-system read-access to a PHP web application.

Using this library involves three main components:

1. A public/private key pair.
2. A web server for receiving uploaded documents. Uploaded documents are encrypted using the public key immediately upon upload, and then the unencrypted document is immediately destroyed.
3. A file server from which your authorized users can retrieve uploaded documents. You will provide a process which retrieves encrypted documents from the web server and decrypts these documents onto the file server using the private key. Of course, you are responsible for maintaining a file server which you trust to host these unencrypted documents.

Note that the private key does not live on web server. If an attacker were to gain read-access to your web server while there were documents waiting to be moved to your file server, then the attacker would only see a set of encrypted documents and they would not be able to retrieve the private key which would give them the ability to decrypt these documents.


## Example

For this example, we assume that:

1. Your web server is running Apache, \*nix, and of course PHP.
2. Your file server may be either \*nix or Windows.
3. We use (Composer)[https://getcomposer.org/] for package management, but you can modify the example to work without Composer.

This example *does not answer* how to move the encrypted files from the web server to the file server. On \*nix, you might choose to move them with an `rsync --delete ...` command. On Windows, you could use WinSCP. Using an `authorized_keys` file, it's possible to create an automated job on either \*nix or Windows which could move these files over automatically.

### Create a Private/Public Key Pair

To create a private, public key pair in \*nix:

```
  openssl genrsa -out my_key_name.pem 4096
  openssl rsa -in my_key_name.pem -pubout > my_key_name.pub
```

You should put the public copy of your key (`my_key_name.pub`) onto your web server. But you should **not** put the private copy of your key (`my_key_name.pem`) onto your web server. The private copy of your key will need to be on your file server.

### Sample Web Application

Web application structure:
```
mywebapp
├── composer.json
├── index.php
├── cert
    ├── .htaccess
    └── my_key_name.pub
└── uploads
    └── .htaccess
```

The `uploads` directory must be writable to your Apache user. For example, you might use `chmod o+w uploads`.

The `composer.json` specifies the `uwdoem/secure-upload` package as a requirement. You'll need to run `composer install` to install this package and the `vendor` directory.

composer.json:
```
{
    "require": {
        "uwdoem/secure-upload": "^0.2.0"
    }
}
```


We place `.htaccess` files that block visitor access to the `cert` and `uploads` directories.

cert/.htaccess:
```
deny from all
```
uploads/.htaccess:
```
deny from all
```


The `index.php` is our primary page.

index.php:
```
<?php

require_once(__DIR__ . '/vendor/autoload.php');

use UWDOEM\SecureUploads\Cipher;

// Turn on error reporting, but only for troubleshooting and development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'GET') { ?>

<html>
<body>
<form method="POST" action="." enctype="multipart/form-data">
    <input type="file" name="myFile">
    <input type="submit" value="Submit">
</form>
</body>
</html>

<?php } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $thereWereUploadErrors = $_FILES['myFile']['error'] || $_FILES['myFile']['size'] === 0;
    if ($thereWereUploadErrors) { echo "Error uploading document"; die(); }

    // The path to your public key
    $publicKeyLocation = __DIR__ . '/cert/my_key_name.pub';

    // The path of the file, as uploaded by Apache
    $fileLocation = $_FILES['myFile']['tmp_name'];

    // The path to your uploads directory, where encrypted files shall be
    // stored.
    $destination = __DIR__ . '/uploads/';

    // The name of the uploaded file, as chosen by the submitter
    $oldFilename = $_FILES['myFile']['name'];

    // Here we determine what the name of the file will be when it is
    // decrypted onto the file server. The `Cipher` class provides a static
    // method for "scrubbing" the file name, but you could also choose to
    // prepend the file name with some identifying information, such as the
    // visitor's UWNetID, if you're forcing NetID authentication.
    $newFilename = Cipher::cleanFilename($oldFilename);

    Cipher::encrypt($newFilename, $fileLocation, $destination, $publicKeyLocation);

    ?>
<html>
<body>
<p>Your file has been uploaded.</p>
</body>
</html>

<?php
}
```


For each file that is uploaded, four encryption files will be created. For example `719b5e92a27aefb858982131e8d3be56.data`, `719b5e92a27aefb858982131e8d3be56.data.key`, `719b5e92a27aefb858982131e8d3be56.info`, and `719b5e92a27aefb858982131e8d3be56.info.key`. Each uploaded file will have its own unique hash, prefixing the `.data`, `.data.key`, `.info`, and `.info.key` files.

All four of these files must be moved to your decryption script in order to decrypt the uploaded file.


### Sample Decryption Script

In the tree below, I have uploaded two documents using the web application above and moved the resulting files into my decryption scrypt:

```
mydecrypter
├── cert
│   └── my_key_name.pem
├── composer.json
├── decrypt.php
├── in
│   ├── 719b5e92a27aefb858982131e8d3be56.data
│   ├── 719b5e92a27aefb858982131e8d3be56.data.key
│   ├── 719b5e92a27aefb858982131e8d3be56.info
│   ├── 719b5e92a27aefb858982131e8d3be56.info.key
│   ├── 7a3f189af2fc309128e144f2fc3d419e.data
│   ├── 7a3f189af2fc309128e144f2fc3d419e.data.key
│   ├── 7a3f189af2fc309128e144f2fc3d419e.info
│   └── 7a3f189af2fc309128e144f2fc3d419e.info.key
├── out
└── processed
```

The `composer.json` specifies the `uwdoem/secure-upload` package as a requirement. You'll need to run `composer install` to install this package and the `vendor` directory.

composer.json:
```
{
    "require": {
        "uwdoem/secure-upload": "^0.2.0"
    }
}
```


Here is the script which performs the decryption. Having placed your encrypted data files into the `in` directory, you would invoke the script using `php decrypt.php`.

decrypt.php:
```
<?php

require_once dirname(__FILE__) . '/vendor/autoload.php';

use UWDOEM\SecureUploads\Cipher;


// The directory where the encrypted files are
$in = __DIR__ . '/in/';

// The directory that files shall be decrypted to
$out = __DIR__ . '/out/';

// A directory to put encrypted files after they have been decrypted
$processed = __DIR__ . '/processed/';

// The path to your pirvate key
$privateKeyLocation = __DIR__ . '/cert/my_key_name.pem';

// Scan through all of the files in the input directory...
$files = scandir($in);
foreach ($files as $file) {
    // If this is a data file...
    if (pathinfo($file, PATHINFO_EXTENSION) === 'data') {

        // Then identify the hash...
        $hash = strtok($file, '.');

        // Decrypt the file to the $out directory...
        Cipher::decrypt($in . $file, $out, $privateKeyLocation);

        // And move all of the encrypted/key/data files for this upload into
        // the $processed directory.
        foreach (["data", "data.key", "info", "info.key"] as $suffix) {
            rename("$in//$hash.$suffix", "$processed//$hash.$suffix");
        }
    }
}

```
