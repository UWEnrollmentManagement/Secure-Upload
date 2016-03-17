[![Build Status](https://travis-ci.org/UWEnrollmentManagement/secure-upload.svg?branch=master)](https://travis-ci.org/UWEnrollmentManagement/secure-upload)

# secure-upload

## Instructions

To create a private, public key pair in *nix:

```
  openssl genrsa -out my_key_name.pem 4096
  openssl rsa -in my_key_name.pem -pubout > my_key_name.pub
```
