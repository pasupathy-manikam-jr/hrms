<?php

namespace Database\Seeders\Modules;

use App\Models\Media;
use App\Models\MediaDirectory;
use App\Models\User;
use App\Support\SimplePdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaSeeder extends Seeder
{
    /**
     * Seed the demo's media folders and files, with small generated placeholders instead of the demo's files.
     */
    public function run(): void
    {
        /** @var array{directories: list<string>, media: list<array{name: string, file_name: string, directory: string|null}>} $data */
        $data = File::json(database_path('demo/media.json'), JSON_THROW_ON_ERROR);
        $owner = User::query()->where('email', 'company@example.com')->value('id');

        $directories = collect($data['directories'])->mapWithKeys(fn (string $name) => [
            $name => MediaDirectory::query()->firstOrCreate(['name' => $name], ['created_by' => $owner])->id,
        ]);

        foreach ($data['media'] as $item) {
            if (Media::query()->where('name', $item['name'])->exists()) {
                continue;
            }

            $extension = pathinfo($item['file_name'], PATHINFO_EXTENSION);
            $path = Media::UPLOAD_DIRECTORY.'/'.Str::random(40).'.'.$extension;
            $contents = $extension === 'png' ? $this->placeholderImage($item['name']) : SimplePdf::make([$item['name']]);
            Storage::disk('local')->put($path, $contents);

            Media::query()->create([
                'name' => $item['name'],
                'directory_id' => $directories[$item['directory']] ?? null,
                'file_path' => $path,
                'file_name' => $item['file_name'],
                'file_type' => $extension === 'png' ? 'image/png' : 'application/pdf',
                'file_size' => strlen($contents),
                'created_by' => $owner,
            ]);
        }
    }

    /**
     * A 320x180 tile in a colour derived from the name.
     */
    private function placeholderImage(string $name): string
    {
        $image = imagecreatetruecolor(320, 180);
        $hash = md5($name, true);
        [$r, $g, $b] = [96 + ord($hash[0]) % 128, 96 + ord($hash[1]) % 128, 96 + ord($hash[2]) % 128];
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));
        imagestring($image, 5, 12, 80, $name, (int) imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
