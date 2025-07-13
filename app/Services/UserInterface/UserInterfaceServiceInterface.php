<?php

namespace App\Services\UserInterface;

interface UserInterfaceServiceInterface
{
    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode, string $phoneNumber): array;
} 