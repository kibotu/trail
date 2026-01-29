<?php

declare(strict_types=1);

namespace Trail\Services;

/**
 * Service for encoding/decoding entry IDs to prevent enumeration attacks
 * Uses a reversible hash with salt to obscure sequential IDs
 */
class HashIdService
{
    private string $salt;
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const MIN_LENGTH = 8;

    public function __construct(string $salt)
    {
        if (empty($salt)) {
            throw new \InvalidArgumentException('Salt cannot be empty');
        }
        $this->salt = $salt;
    }

    /**
     * Encode an entry ID to a hash string
     * 
     * @param int $id Entry ID
     * @return string Encoded hash
     */
    public function encode(int $id): string
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('ID must be positive');
        }

        // Mix the ID with salt using a reversible operation
        $mixed = $this->mix($id);
        
        // Convert to base62
        $hash = $this->toBase62($mixed);
        
        // Pad to minimum length for consistency
        $hash = str_pad($hash, self::MIN_LENGTH, '0', STR_PAD_LEFT);
        
        return $hash;
    }

    /**
     * Decode a hash string back to an entry ID
     * 
     * @param string $hash Encoded hash
     * @return int|null Entry ID or null if invalid
     */
    public function decode(string $hash): ?int
    {
        if (empty($hash) || !ctype_alnum($hash)) {
            return null;
        }

        try {
            // Convert from base62
            $mixed = $this->fromBase62($hash);
            
            if ($mixed === null) {
                return null;
            }
            
            // Unmix to get original ID
            $id = $this->unmix($mixed);
            
            // Validate result
            if ($id < 1 || $id > PHP_INT_MAX) {
                return null;
            }
            
            return $id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mix ID with salt using XOR and rotation
     * This is reversible but obscures the original value
     * 
     * @param int $id Original ID
     * @return int Mixed value
     */
    private function mix(int $id): int
    {
        // Generate a numeric key from salt (ensure unsigned)
        $key = crc32($this->salt);
        if ($key < 0) {
            $key = $key & 0xFFFFFFFF;
        }
        
        // XOR with key
        $mixed = $id ^ $key;
        
        // Add some bit rotation for additional obscurity
        $rotated = (($mixed << 13) | ($mixed >> (32 - 13))) & 0xFFFFFFFF;
        
        return $rotated;
    }

    /**
     * Unmix to get original ID
     * 
     * @param int $mixed Mixed value
     * @return int Original ID
     */
    private function unmix(int $mixed): int
    {
        // Reverse the rotation
        $unrotated = (($mixed >> 13) | ($mixed << (32 - 13))) & 0xFFFFFFFF;
        
        // Generate the same key from salt (ensure unsigned)
        $key = crc32($this->salt);
        if ($key < 0) {
            $key = $key & 0xFFFFFFFF;
        }
        
        // XOR with key to get original
        $id = $unrotated ^ $key;
        
        return $id;
    }

    /**
     * Convert integer to base62 string
     * 
     * @param int $num Number to convert
     * @return string Base62 string
     */
    private function toBase62(int $num): string
    {
        if ($num === 0) {
            return self::ALPHABET[0];
        }

        $base = strlen(self::ALPHABET);
        $result = '';
        
        while ($num > 0) {
            $remainder = $num % $base;
            $result = self::ALPHABET[$remainder] . $result;
            $num = (int)($num / $base);
        }
        
        return $result;
    }

    /**
     * Convert base62 string to integer
     * 
     * @param string $str Base62 string
     * @return int|null Decoded number or null if invalid
     */
    private function fromBase62(string $str): ?int
    {
        $base = strlen(self::ALPHABET);
        $result = 0;
        $length = strlen($str);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $str[$i];
            $pos = strpos(self::ALPHABET, $char);
            
            if ($pos === false) {
                return null;
            }
            
            $result = $result * $base + $pos;
        }
        
        return $result;
    }
}
