<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WatchlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tickers = [
            // === Your pinned counters ===
            ['ticker' => 'TPC',   'company_name' => 'Tutor Perini Corporation',    'exchange' => 'NYSE',   'sector' => 'Construction',          'is_pinned' => true],
            ['ticker' => 'BCS',   'company_name' => 'Barclays PLC',                'exchange' => 'NYSE',   'sector' => 'Banking',               'is_pinned' => true],
            ['ticker' => 'TE',    'company_name' => 'T1 Energy Inc.',              'exchange' => 'NYSE',   'sector' => 'Energy',                'is_pinned' => true],
            ['ticker' => 'VLN',   'company_name' => 'Valens Semiconductor Ltd.',   'exchange' => 'NYSE',   'sector' => 'Semiconductors',        'is_pinned' => true],
            ['ticker' => 'SKIL',  'company_name' => 'Skillsoft Corp.',             'exchange' => 'NYSE',   'sector' => 'Technology',            'is_pinned' => true],

            // === Top Revolut-popular / high-momentum counters ===
            ['ticker' => 'NVDA',  'company_name' => 'NVIDIA Corporation',          'exchange' => 'NASDAQ', 'sector' => 'Semiconductors',        'is_pinned' => false],
            ['ticker' => 'AAPL',  'company_name' => 'Apple Inc.',                  'exchange' => 'NASDAQ', 'sector' => 'Technology',            'is_pinned' => false],
            ['ticker' => 'TSLA',  'company_name' => 'Tesla Inc.',                  'exchange' => 'NASDAQ', 'sector' => 'Automotive/EV',         'is_pinned' => false],
            ['ticker' => 'MSFT',  'company_name' => 'Microsoft Corporation',       'exchange' => 'NASDAQ', 'sector' => 'Technology',            'is_pinned' => false],
            ['ticker' => 'GOOGL', 'company_name' => 'Alphabet Inc.',               'exchange' => 'NASDAQ', 'sector' => 'Technology',            'is_pinned' => false],
            ['ticker' => 'AMZN',  'company_name' => 'Amazon.com Inc.',             'exchange' => 'NASDAQ', 'sector' => 'E-Commerce/Cloud',      'is_pinned' => false],
            ['ticker' => 'META',  'company_name' => 'Meta Platforms Inc.',         'exchange' => 'NASDAQ', 'sector' => 'Social Media',          'is_pinned' => false],
            ['ticker' => 'AMD',   'company_name' => 'Advanced Micro Devices',      'exchange' => 'NASDAQ', 'sector' => 'Semiconductors',        'is_pinned' => false],
            ['ticker' => 'PLTR',  'company_name' => 'Palantir Technologies',       'exchange' => 'NYSE',   'sector' => 'AI/Data Analytics',     'is_pinned' => false],
            ['ticker' => 'COIN',  'company_name' => 'Coinbase Global Inc.',        'exchange' => 'NASDAQ', 'sector' => 'Crypto/Fintech',        'is_pinned' => false],
            ['ticker' => 'SOFI',  'company_name' => 'SoFi Technologies Inc.',      'exchange' => 'NASDAQ', 'sector' => 'Fintech',               'is_pinned' => false],
            ['ticker' => 'RIVN',  'company_name' => 'Rivian Automotive Inc.',      'exchange' => 'NASDAQ', 'sector' => 'Automotive/EV',         'is_pinned' => false],
            ['ticker' => 'LCID',  'company_name' => 'Lucid Group Inc.',            'exchange' => 'NASDAQ', 'sector' => 'Automotive/EV',         'is_pinned' => false],
            ['ticker' => 'SMCI',  'company_name' => 'Super Micro Computer Inc.',   'exchange' => 'NASDAQ', 'sector' => 'AI Infrastructure',     'is_pinned' => false],
            ['ticker' => 'MSTR',  'company_name' => 'MicroStrategy Inc.',          'exchange' => 'NASDAQ', 'sector' => 'Bitcoin/Software',      'is_pinned' => false],
            ['ticker' => 'SHOP',  'company_name' => 'Shopify Inc.',                'exchange' => 'NYSE',   'sector' => 'E-Commerce',            'is_pinned' => false],
            ['ticker' => 'XYZ',   'company_name' => 'Block Inc.',                  'exchange' => 'NYSE',   'sector' => 'Fintech',               'is_pinned' => false],
            ['ticker' => 'PYPL',  'company_name' => 'PayPal Holdings Inc.',        'exchange' => 'NASDAQ', 'sector' => 'Fintech',               'is_pinned' => false],
            ['ticker' => 'RKLB',  'company_name' => 'Rocket Lab USA Inc.',         'exchange' => 'NASDAQ', 'sector' => 'Aerospace',             'is_pinned' => false],
            ['ticker' => 'IONQ',  'company_name' => 'IonQ Inc.',                   'exchange' => 'NYSE',   'sector' => 'Quantum Computing',     'is_pinned' => false],
            ['ticker' => 'RGTI',  'company_name' => 'Rigetti Computing Inc.',      'exchange' => 'NASDAQ', 'sector' => 'Quantum Computing',     'is_pinned' => false],
            ['ticker' => 'SOUN',  'company_name' => 'SoundHound AI Inc.',          'exchange' => 'NASDAQ', 'sector' => 'AI/Voice',              'is_pinned' => false],
            ['ticker' => 'BBAI',  'company_name' => 'BigBear.ai Holdings Inc.',    'exchange' => 'NYSE',   'sector' => 'AI/Defense',            'is_pinned' => false],
            ['ticker' => 'ACHR',  'company_name' => 'Archer Aviation Inc.',        'exchange' => 'NYSE',   'sector' => 'eVTOL/Aviation',        'is_pinned' => false],
            ['ticker' => 'JOBY',  'company_name' => 'Joby Aviation Inc.',          'exchange' => 'NYSE',   'sector' => 'eVTOL/Aviation',        'is_pinned' => false],
        ];

        foreach ($tickers as $data) {
            \App\Models\Watchlist::updateOrCreate(
                ['ticker' => $data['ticker']],
                array_merge($data, ['active' => true])
            );
        }
    }
}
