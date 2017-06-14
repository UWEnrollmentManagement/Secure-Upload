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

### Sample Application

```
myapp
├── composer.json
├── index.php
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

uploads/.htaccess:
```
deny from all

```


