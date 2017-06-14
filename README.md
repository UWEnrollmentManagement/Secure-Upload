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

### Create a Private/Public Key Pair

To create a private, public key pair in \*nix:

```
  openssl genrsa -out my_key_name.pem 4096
  openssl rsa -in my_key_name.pem -pubout > my_key_name.pub
```

You should put the public copy of your key (`my_key_name.pub`) onto your web server. But you should **not** put the private copy of your key (`my_key_name.pem`) onto your web server. The private copy of your key will need to be on your file server.

### Sample Web Application

```
myapp
├── composer.json
├── index.php
├── cert
    ├── .htaccess
    └── my_key_name.pub
└── uploads
    └── .htaccess
```

composer.json:
```
{
    "require": {
        "uwdoem/secure-upload": "^0.2.0"
    }
}
```

cert/.htaccess:
```
deny from all
```

uploads/.htaccess:
```
deny from all
```

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


