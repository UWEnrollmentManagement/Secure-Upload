<?php

namespace UWDOEM\SecureUploads;

/**
 * Class Cipher encrypts and decrypts files using public key cryptography.
 *
 * @package UWDOEM\SecureUploads
 */
class Cipher
{

    /**
     * White list scrub of file name.
     *
     * @param string $filename
     * @return string
     */
    public static function cleanFilename($filename)
    {
        return preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            htmlentities(pathinfo($filename, PATHINFO_FILENAME))
        ) . '.' . preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            htmlentities(pathinfo($filename, PATHINFO_EXTENSION))
        );
    }

    /**
     * Encrypt a file specified in the $_FILES global.
     *
     * @param string $name
     * @param string $location
     * @param string $destination
     * @param string $publicKeyLocation
     * @return string
     */
    public static function encrypt($name, $location, $destination, $publicKeyLocation)
    {
        /** @var resource $publicKey */
        $publicKey = openssl_get_publickey(file_get_contents($publicKeyLocation));

        /** @var string $data */
        $data = file_get_contents($location);
        /** @var string $info */
        $info = json_encode(['name' => $name]);

        openssl_seal(gzcompress($data), $encryptedData, $dataKeys, [$publicKey]);
        openssl_seal(gzcompress($info), $encryptedInfo, $infoKeys, [$publicKey]);

        /** @var string $hash */
        $hash = md5($encryptedData);

        file_put_contents("$destination/$hash.data", $encryptedData);
        file_put_contents("$destination/$hash.info", $encryptedInfo);

        file_put_contents("$destination/$hash.data.key", $dataKeys[0]);
        file_put_contents("$destination/$hash.info.key", $infoKeys[0]);

        openssl_free_key($publicKey);

        return "$destination/$hash.data";
    }

    /**
     * @param string $filePath
     * @param string $destination
     * @param string $privateKeyLocation
     * @return string
     * @throws \Exception if ::decrypt is not able to decrypt the files.
     */
    public static function decrypt($filePath, $destination, $privateKeyLocation)
    {
        /** @var string $dir */
        $dir = dirname($filePath);
        /** @var string $hash */
        $hash = basename($filePath, ".data");

        /** @var resource $privateKeyId */
        $privateKeyId = openssl_get_privatekey(file_get_contents($privateKeyLocation));

        /** @var string $encryptedData */
        $encryptedData = file_get_contents($filePath);
        /** @var string $encryptedInfo */
        $encryptedInfo = file_get_contents("$dir/$hash.info");

        /** @var string $dataKey */
        $dataKey = file_get_contents("$dir/$hash.data.key");
        /** @var string $dataKey */
        $infoKey = file_get_contents("$dir/$hash.info.key");

        /** @var boolean $dataWasDecrypted */
        $dataWasDecrypted = openssl_open($encryptedData, $decryptedData, $dataKey, $privateKeyId);
        /** @var boolean $infoWasDecrypted */
        $infoWasDecrypted = openssl_open($encryptedInfo, $decryptedInfo, $infoKey, $privateKeyId);

        if ($dataWasDecrypted === true && $infoWasDecrypted === true) {
            /** @var string[] $info */
            $info = json_decode(gzuncompress($decryptedInfo), true);

            /** @var string $filename */
            $filename = static::cleanFilename($info['name']);

            file_put_contents("$destination/$filename", gzuncompress($decryptedData));
        } else {
            throw new \Exception('Failed to decrypt file.');
        }

        openssl_free_key($privateKeyId);

        return "$destination/$filename";
    }
}
