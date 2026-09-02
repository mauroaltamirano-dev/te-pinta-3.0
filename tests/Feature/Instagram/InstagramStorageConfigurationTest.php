<?php

namespace Tests\Feature\Instagram;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InstagramStorageConfigurationTest extends TestCase
{
    public function test_testing_local_disk_is_isolated_from_development_private_media(): void
    {
        $root = (string) config('filesystems.disks.local.root');

        $this->assertSame(storage_path('framework/testing/disks/local'), $root);
        $this->assertNotSame(storage_path('app/private'), $root);
    }

    public function test_private_local_disk_creates_group_writable_non_public_story_media(): void
    {
        $root = (string) config('filesystems.disks.local.root');
        $directory = 'instagram/storage-permissions-'.Str::uuid();
        $path = $directory.'/story.jpg';
        $previousUmask = umask(0007);

        Storage::forgetDisk('local');

        try {
            $this->assertTrue(Storage::disk('local')->put($path, 'jpeg-bytes'));
            $this->assertSame(0770, fileperms($root.'/'.$directory) & 0777);
            $this->assertSame(0660, fileperms($root.'/'.$path) & 0777);
        } finally {
            Storage::disk('local')->deleteDirectory($directory);
            umask($previousUmask);
        }
    }
}
