<?php

namespace Database\Seeders;

use App\Models\Item;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemSeeder extends Seeder
{
    /**
     * Fake eyewear products for local development, each with a drawn image.
     *
     * @var array<int, array{name: string, description: string, shape: string, frame: array{int, int, int}, lens: array{int, int, int}, background: array{int, int, int}, is_active: bool, sale_price: float, pieces_per_packet: ?int}>
     */
    private array $products = [
        ['name' => 'Classic Round Glasses', 'description' => 'Round metal frame, light and comfortable for daily use.', 'shape' => 'round', 'frame' => [40, 40, 40], 'lens' => [220, 235, 245], 'background' => [245, 240, 230], 'is_active' => true, 'sale_price' => 25000, 'pieces_per_packet' => 24],
        ['name' => 'Square Black Frame', 'description' => 'Bold square acetate frame in matte black.', 'shape' => 'square', 'frame' => [20, 20, 20], 'lens' => [230, 240, 250], 'background' => [236, 242, 248], 'is_active' => true, 'sale_price' => 30000, 'pieces_per_packet' => 20],
        ['name' => 'Aviator Sunglasses', 'description' => 'Gold aviator frame with dark green lenses and UV400 protection.', 'shape' => 'aviator', 'frame' => [190, 150, 60], 'lens' => [40, 80, 60], 'background' => [250, 244, 225], 'is_active' => true, 'sale_price' => 45000, 'pieces_per_packet' => 20],
        ['name' => 'Cat Eye Tortoise', 'description' => 'Vintage cat eye frame with a tortoise shell pattern.', 'shape' => 'cateye', 'frame' => [120, 70, 30], 'lens' => [245, 230, 215], 'background' => [248, 236, 228], 'is_active' => true, 'sale_price' => 35000, 'pieces_per_packet' => 24],
        ['name' => 'Blue Light Blocking Glasses', 'description' => 'Protects the eyes from screen light. Transparent frame.', 'shape' => 'square', 'frame' => [90, 140, 200], 'lens' => [210, 230, 255], 'background' => [232, 240, 252], 'is_active' => true, 'sale_price' => 20000, 'pieces_per_packet' => 24],
        ['name' => 'Kids Red Glasses', 'description' => 'Flexible and durable frame for children.', 'shape' => 'round', 'frame' => [210, 40, 50], 'lens' => [240, 240, 245], 'background' => [252, 234, 234], 'is_active' => true, 'sale_price' => 15000, 'pieces_per_packet' => 20],
        ['name' => 'Sport Wrap Sunglasses', 'description' => 'Wide lenses for running and cycling.', 'shape' => 'aviator', 'frame' => [30, 30, 30], 'lens' => [200, 90, 30], 'background' => [238, 238, 238], 'is_active' => false, 'sale_price' => 40000, 'pieces_per_packet' => 20],
        ['name' => 'Rimless Titanium Glasses', 'description' => null, 'shape' => 'rimless', 'frame' => [150, 150, 160], 'lens' => [235, 245, 250], 'background' => [244, 246, 248], 'is_active' => true, 'sale_price' => 60000, 'pieces_per_packet' => 24],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->products as $product) {
            $path = 'items/'.Str::slug($product['name']).'-'.Str::random(6).'.png';

            Storage::disk('public')->put($path, $this->drawGlasses($product));

            Item::create([
                'name' => $product['name'],
                'description' => $product['description'],
                'image' => $path,
                'is_active' => $product['is_active'],
                'sale_price' => $product['sale_price'],
                'pieces_per_packet' => $product['pieces_per_packet'],
            ]);
        }
    }

    /**
     * Draws a simple pair of glasses and returns the PNG bytes.
     *
     * @param  array{shape: string, frame: array{int, int, int}, lens: array{int, int, int}, background: array{int, int, int}}  $product
     */
    private function drawGlasses(array $product): string
    {
        $image = imagecreatetruecolor(600, 400);
        imageantialias($image, true);

        $background = imagecolorallocate($image, ...$product['background']);
        $frame = imagecolorallocate($image, ...$product['frame']);
        $lens = imagecolorallocate($image, ...$product['lens']);

        imagefilledrectangle($image, 0, 0, 600, 400, $background);
        imagesetthickness($image, 10);

        foreach ([180, 420] as $centerX) {
            $this->drawLens($image, $product['shape'], $centerX, 200, $frame, $lens);
        }

        imagearc($image, 300, 205, 70, 50, 200, 340, $frame);
        imageline($image, 70, 185, 20, 170, $frame);
        imageline($image, 530, 185, 580, 170, $frame);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Draws one lens with its rim, centered on the given point.
     */
    private function drawLens(GdImage $image, string $shape, int $centerX, int $centerY, int $frame, int $lens): void
    {
        match ($shape) {
            'square' => $this->drawRectangleLens($image, $centerX - 105, $centerY - 70, $centerX + 105, $centerY + 70, $frame, $lens),
            'aviator' => $this->drawEllipseLens($image, $centerX, $centerY + 10, 220, 170, $frame, $lens),
            'cateye' => $this->drawCatEyeLens($image, $centerX, $centerY, $frame, $lens),
            'rimless' => imagefilledellipse($image, $centerX, $centerY, 210, 140, $lens),
            default => $this->drawEllipseLens($image, $centerX, $centerY, 180, 180, $frame, $lens),
        };
    }

    private function drawEllipseLens(GdImage $image, int $centerX, int $centerY, int $width, int $height, int $frame, int $lens): void
    {
        imagefilledellipse($image, $centerX, $centerY, $width, $height, $frame);
        imagefilledellipse($image, $centerX, $centerY, $width - 20, $height - 20, $lens);
    }

    private function drawRectangleLens(GdImage $image, int $left, int $top, int $right, int $bottom, int $frame, int $lens): void
    {
        imagefilledrectangle($image, $left, $top, $right, $bottom, $frame);
        imagefilledrectangle($image, $left + 10, $top + 10, $right - 10, $bottom - 10, $lens);
    }

    /**
     * A cat eye lens: a wide ellipse with pointed upper outer corners.
     */
    private function drawCatEyeLens(GdImage $image, int $centerX, int $centerY, int $frame, int $lens): void
    {
        $outerX = $centerX < 300 ? $centerX - 120 : $centerX + 120;

        imagefilledpolygon($image, [$centerX, $centerY - 60, $outerX, $centerY - 85, $outerX, $centerY - 20], $frame);
        $this->drawEllipseLens($image, $centerX, $centerY + 5, 220, 140, $frame, $lens);
    }
}
