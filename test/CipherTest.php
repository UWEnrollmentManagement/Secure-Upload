<?php

namespace Athens\Encryption\Test;

use PHPUnit_Framework_TestCase;

use UWDOEM\SecureUploads\Cipher;

class CipherTest extends PHPUnit_Framework_TestCase
{
    public function testEncryptDecrypt()
    {
        if (!is_dir(__DIR__ . '/tmp')) {
            mkdir(__DIR__ . '/tmp');
        }

        if (!is_dir(__DIR__ . '/tmp/out')) {
            mkdir(__DIR__ . '/tmp/out');
        }

        $word = str_shuffle('abcdef');

        $data = str_repeat($word, rand(10, 30));
        for($i = 0; $i < 100; $i++) {
            $data .= str_shuffle('abcdefeghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        }

        $filename = md5(rand()) . '.txt';
        $fileLocation = __DIR__ . '/tmp/' . $filename;

        file_put_contents($fileLocation, $data);

        $location = Cipher::encrypt($filename, $fileLocation, __DIR__ . '/tmp', __DIR__ . '/certs/publickey.cer');

        Cipher::decrypt($location, __DIR__ . '/tmp/out/' , __DIR__ . '/certs/privatekey.pem');

        // Delete everything in the tmp directory
        array_map('unlink', glob(__DIR__ . "/tmp/out/*"));
        rmdir(__DIR__ . '/tmp/out');
        array_map('unlink', glob(__DIR__ . "/tmp/*"));
        rmdir(__DIR__ . '/tmp');
    }
}
