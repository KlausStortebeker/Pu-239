<?php

declare(strict_types = 1);

/**
 * Class block_stdhead.
 * @package Pu239
 */
class block_stdhead
{
    const STDHEAD_DEMOTION = 0x1; // 1
    const STDHEAD_STAFF_MESSAGE = 0x2; // 2
    const STDHEAD_NEWPM = 0x4; // 4
    const STDHEAD_UPLOADAPP = 0x8; // 8.
    const STDHEAD_REPORTS = 0x10; // 16
    const STDHEAD_FREELEECH = 0x20; // 32
    const STDHEAD_HAPPYHOUR = 0x40; // 64
    const STDHEAD_CRAZYHOUR = 0x80; // 128
    const STDHEAD_BUG_MESSAGE = 0x100; // 256
    const STDHEAD_FREELEECH_CONTRIBUTION = 0x200; // 512
    const STDHEAD_STAFF_MENU = 0x400; // 1024
    const STDHEAD_FLASH_MESSAGES = 0x800; // 2048
}
