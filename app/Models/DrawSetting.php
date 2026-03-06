<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawSetting extends Model
{
    use HasFactory;
    
    protected $fillable = [
      'draw_id',
      'draw_format_id',
      'draw_type_id',
      'boxes',
      'playoff_size',
      'num_sets',
      'playoff_config',  // JSON: playoff brackets configuration
      'preset_key',      // Store which preset template was used
    ];

    protected $casts = [
      'playoff_config' => 'array',
    ];

    /**
     * Get available preset templates
     * Max 40 players per draw
     */
    public static function getPresetTemplates(): array
    {
      return [
        // ============================================================
        // 1 GROUP
        // ============================================================
        '1_group_4s' => [
          'name' => '1 Group: 1-4, 5-8, 9-12... (4-player brackets)',
          'groups' => 1,
          'max_positions' => 40,
          'config' => [
            ['name' => 'Main Draw (1-4)', 'slug' => 'main', 'size' => 4, 'positions' => [1,2,3,4], 'enabled' => true],
            ['name' => 'Plate (5-8)', 'slug' => 'plate', 'size' => 4, 'positions' => [5,6,7,8], 'enabled' => true],
            ['name' => 'Consolation (9-12)', 'slug' => 'cons', 'size' => 4, 'positions' => [9,10,11,12], 'enabled' => true],
            ['name' => 'Bowl (13-16)', 'slug' => 'bowl', 'size' => 4, 'positions' => [13,14,15,16], 'enabled' => false],
            ['name' => 'Shield (17-20)', 'slug' => 'shield', 'size' => 4, 'positions' => [17,18,19,20], 'enabled' => false],
            ['name' => 'Spoon (21-24)', 'slug' => 'spoon', 'size' => 4, 'positions' => [21,22,23,24], 'enabled' => false],
          ],
        ],
        '1_group_8s' => [
          'name' => '1 Group: 1-8, 9-16, 17-24... (8-player brackets)',
          'groups' => 1,
          'max_positions' => 40,
          'config' => [
            ['name' => 'Main Draw (1-8)', 'slug' => 'main', 'size' => 8, 'positions' => [1,2,3,4,5,6,7,8], 'enabled' => true],
            ['name' => 'Plate (9-16)', 'slug' => 'plate', 'size' => 8, 'positions' => [9,10,11,12,13,14,15,16], 'enabled' => true],
            ['name' => 'Consolation (17-24)', 'slug' => 'cons', 'size' => 8, 'positions' => [17,18,19,20,21,22,23,24], 'enabled' => true],
            ['name' => 'Bowl (25-32)', 'slug' => 'bowl', 'size' => 8, 'positions' => [25,26,27,28,29,30,31,32], 'enabled' => false],
            ['name' => 'Shield (33-40)', 'slug' => 'shield', 'size' => 8, 'positions' => [33,34,35,36,37,38,39,40], 'enabled' => false],
          ],
        ],
        
        // ============================================================
        // 2 GROUPS
        // ============================================================
        '2_groups_4s' => [
          'name' => '2 Groups: 1-4, 5-8, 9-12... (4-player brackets)',
          'groups' => 2,
          'max_positions' => 20,
          'config' => [
            ['name' => 'Main Draw (1-4)', 'slug' => 'main', 'size' => 4, 'positions' => [1,2], 'enabled' => true],
            ['name' => 'Plate (5-8)', 'slug' => 'plate', 'size' => 4, 'positions' => [3,4], 'enabled' => true],
            ['name' => 'Consolation (9-12)', 'slug' => 'cons', 'size' => 4, 'positions' => [5,6], 'enabled' => true],
            ['name' => 'Bowl (13-16)', 'slug' => 'bowl', 'size' => 4, 'positions' => [7,8], 'enabled' => false],
            ['name' => 'Shield (17-20)', 'slug' => 'shield', 'size' => 4, 'positions' => [9,10], 'enabled' => false],
            ['name' => 'Spoon (21-24)', 'slug' => 'spoon', 'size' => 4, 'positions' => [11,12], 'enabled' => false],
            ['name' => '25-28', 'slug' => 'p7', 'size' => 4, 'positions' => [13,14], 'enabled' => false],
            ['name' => '29-32', 'slug' => 'p8', 'size' => 4, 'positions' => [15,16], 'enabled' => false],
            ['name' => '33-36', 'slug' => 'p9', 'size' => 4, 'positions' => [17,18], 'enabled' => false],
            ['name' => '37-40', 'slug' => 'p10', 'size' => 4, 'positions' => [19,20], 'enabled' => false],
          ],
        ],
        '2_groups_8s' => [
          'name' => '2 Groups: 1-8, 9-16, 17-24... (8-player brackets)',
          'groups' => 2,
          'max_positions' => 20,
          'config' => [
            ['name' => 'Main Draw (1-8)', 'slug' => 'main', 'size' => 8, 'positions' => [1,2,3,4], 'enabled' => true],
            ['name' => 'Plate (9-16)', 'slug' => 'plate', 'size' => 8, 'positions' => [5,6,7,8], 'enabled' => true],
            ['name' => 'Consolation (17-24)', 'slug' => 'cons', 'size' => 8, 'positions' => [9,10,11,12], 'enabled' => true],
            ['name' => 'Bowl (25-32)', 'slug' => 'bowl', 'size' => 8, 'positions' => [13,14,15,16], 'enabled' => false],
            ['name' => 'Shield (33-40)', 'slug' => 'shield', 'size' => 8, 'positions' => [17,18,19,20], 'enabled' => false],
          ],
        ],

        // ============================================================
        // 3 GROUPS
        // ============================================================
        '3_groups_3s' => [
          'name' => '3 Groups: 1-3, 4-6, 7-9... (3-player brackets)',
          'groups' => 3,
          'max_positions' => 14,
          'config' => [
            ['name' => 'Main Draw (1-3)', 'slug' => 'main', 'size' => 4, 'positions' => [1], 'enabled' => true],
            ['name' => 'Plate (4-6)', 'slug' => 'plate', 'size' => 4, 'positions' => [2], 'enabled' => true],
            ['name' => 'Consolation (7-9)', 'slug' => 'cons', 'size' => 4, 'positions' => [3], 'enabled' => true],
            ['name' => 'Bowl (10-12)', 'slug' => 'bowl', 'size' => 4, 'positions' => [4], 'enabled' => false],
            ['name' => 'Shield (13-15)', 'slug' => 'shield', 'size' => 4, 'positions' => [5], 'enabled' => false],
            ['name' => '16-18', 'slug' => 'p6', 'size' => 4, 'positions' => [6], 'enabled' => false],
            ['name' => '19-21', 'slug' => 'p7', 'size' => 4, 'positions' => [7], 'enabled' => false],
          ],
        ],
        '3_groups_6s' => [
          'name' => '3 Groups: 1-6, 7-12, 13-18... (6-player brackets)',
          'groups' => 3,
          'max_positions' => 14,
          'config' => [
            ['name' => 'Main Draw (1-6)', 'slug' => 'main', 'size' => 8, 'positions' => [1,2], 'enabled' => true],
            ['name' => 'Plate (7-12)', 'slug' => 'plate', 'size' => 8, 'positions' => [3,4], 'enabled' => true],
            ['name' => 'Consolation (13-18)', 'slug' => 'cons', 'size' => 8, 'positions' => [5,6], 'enabled' => true],
            ['name' => 'Bowl (19-24)', 'slug' => 'bowl', 'size' => 8, 'positions' => [7,8], 'enabled' => false],
            ['name' => 'Shield (25-30)', 'slug' => 'shield', 'size' => 8, 'positions' => [9,10], 'enabled' => false],
            ['name' => '31-36', 'slug' => 'p6', 'size' => 8, 'positions' => [11,12], 'enabled' => false],
            ['name' => '37-42', 'slug' => 'p7', 'size' => 8, 'positions' => [13,14], 'enabled' => false],
          ],
        ],
        
        // ============================================================
        // 4 GROUPS
        // ============================================================
        '4_groups_4s' => [
          'name' => '4 Groups: 1-4, 5-8, 9-12... (4-player brackets)',
          'groups' => 4,
          'max_positions' => 10,
          'config' => [
            ['name' => 'Main Draw (1-4)', 'slug' => 'main', 'size' => 4, 'positions' => [1], 'enabled' => true],
            ['name' => 'Plate (5-8)', 'slug' => 'plate', 'size' => 4, 'positions' => [2], 'enabled' => true],
            ['name' => 'Consolation (9-12)', 'slug' => 'cons', 'size' => 4, 'positions' => [3], 'enabled' => true],
            ['name' => 'Bowl (13-16)', 'slug' => 'bowl', 'size' => 4, 'positions' => [4], 'enabled' => false],
            ['name' => 'Shield (17-20)', 'slug' => 'shield', 'size' => 4, 'positions' => [5], 'enabled' => false],
            ['name' => 'Spoon (21-24)', 'slug' => 'spoon', 'size' => 4, 'positions' => [6], 'enabled' => false],
            ['name' => '25-28', 'slug' => 'p7', 'size' => 4, 'positions' => [7], 'enabled' => false],
            ['name' => '29-32', 'slug' => 'p8', 'size' => 4, 'positions' => [8], 'enabled' => false],
            ['name' => '33-36', 'slug' => 'p9', 'size' => 4, 'positions' => [9], 'enabled' => false],
            ['name' => '37-40', 'slug' => 'p10', 'size' => 4, 'positions' => [10], 'enabled' => false],
          ],
        ],
        '4_groups_1to4' => [
          'name' => '4 Groups: 1-4, 5-12, 13-20... (Main=4, others=8)',
          'groups' => 4,
          'max_positions' => 10,
          'config' => [
            ['name' => 'Main Draw (1-4)', 'slug' => 'main', 'size' => 4, 'positions' => [1], 'enabled' => true],
            ['name' => 'Plate (5-12)', 'slug' => 'plate', 'size' => 8, 'positions' => [2,3], 'enabled' => true],
            ['name' => 'Consolation (13-20)', 'slug' => 'cons', 'size' => 8, 'positions' => [4,5], 'enabled' => true],
            ['name' => 'Bowl (21-28)', 'slug' => 'bowl', 'size' => 8, 'positions' => [6,7], 'enabled' => false],
            ['name' => 'Shield (29-36)', 'slug' => 'shield', 'size' => 8, 'positions' => [8,9], 'enabled' => false],
            ['name' => '37-40', 'slug' => 'p6', 'size' => 4, 'positions' => [10], 'enabled' => false],
          ],
        ],
        '4_groups_8s' => [
          'name' => '4 Groups: 1-8, 9-16, 17-24... (8-player brackets)',
          'groups' => 4,
          'max_positions' => 10,
          'config' => [
            ['name' => 'Main Draw (1-8)', 'slug' => 'main', 'size' => 8, 'positions' => [1,2], 'enabled' => true],
            ['name' => 'Plate (9-16)', 'slug' => 'plate', 'size' => 8, 'positions' => [3,4], 'enabled' => true],
            ['name' => 'Consolation (17-24)', 'slug' => 'cons', 'size' => 8, 'positions' => [5,6], 'enabled' => true],
            ['name' => 'Bowl (25-32)', 'slug' => 'bowl', 'size' => 8, 'positions' => [7,8], 'enabled' => false],
            ['name' => 'Shield (33-40)', 'slug' => 'shield', 'size' => 8, 'positions' => [9,10], 'enabled' => false],
          ],
        ],

        // ============================================================
        // 8 GROUPS
        // ============================================================
        '8_groups_8s' => [
          'name' => '8 Groups: 1-8, 9-16, 17-24... (8-player brackets)',
          'groups' => 8,
          'max_positions' => 5,
          'config' => [
            ['name' => 'Main Draw (1-8)', 'slug' => 'main', 'size' => 8, 'positions' => [1], 'enabled' => true],
            ['name' => 'Plate (9-16)', 'slug' => 'plate', 'size' => 8, 'positions' => [2], 'enabled' => true],
            ['name' => 'Consolation (17-24)', 'slug' => 'cons', 'size' => 8, 'positions' => [3], 'enabled' => true],
            ['name' => 'Bowl (25-32)', 'slug' => 'bowl', 'size' => 8, 'positions' => [4], 'enabled' => false],
            ['name' => 'Shield (33-40)', 'slug' => 'shield', 'size' => 8, 'positions' => [5], 'enabled' => false],
          ],
        ],
        '8_groups_16s' => [
          'name' => '8 Groups: 1-16, 17-32, 33-48 (16-player brackets)',
          'groups' => 8,
          'max_positions' => 5,
          'config' => [
            ['name' => 'Main Draw (1-16)', 'slug' => 'main', 'size' => 16, 'positions' => [1,2], 'enabled' => true],
            ['name' => 'Plate (17-32)', 'slug' => 'plate', 'size' => 16, 'positions' => [3,4], 'enabled' => true],
            ['name' => 'Consolation (33-48)', 'slug' => 'cons', 'size' => 16, 'positions' => [5], 'enabled' => false],
          ],
        ],
      ];
    }

    /**
     * Get default playoff configuration
     */
    public static function defaultPlayoffConfig(int $numGroups = 4): array
    {
      // Return 4-player brackets by default
      return [
        ['name' => 'Main Draw (1-4)', 'slug' => 'main', 'size' => 4, 'positions' => [1], 'enabled' => true],
        ['name' => 'Plate (5-8)', 'slug' => 'plate', 'size' => 4, 'positions' => [2], 'enabled' => true],
        ['name' => 'Consolation (9-12)', 'slug' => 'cons', 'size' => 4, 'positions' => [3], 'enabled' => false],
        ['name' => 'Bowl (13-16)', 'slug' => 'bowl', 'size' => 4, 'positions' => [4], 'enabled' => false],
      ];
    }

    /**
     * Get the playoff config or default
     */
    public function getPlayoffConfigAttribute($value): array
    {
      if ($value) {
        return is_string($value) ? json_decode($value, true) : $value;
      }
      return self::defaultPlayoffConfig($this->boxes ?? 4);
    }

    public function drawFormat()
    {
        return $this->belongsTo(\App\Models\DrawFormats::class);
    }

    public function drawType()
    {
        return $this->belongsTo(\App\Models\DrawType::class);
    }

    public function draw()
    {
        return $this->belongsTo(\App\Models\Draw::class);
    }
}
