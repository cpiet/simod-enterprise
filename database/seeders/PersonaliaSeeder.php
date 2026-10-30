<?php

namespace Database\Seeders;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PersonaliaSeeder extends Seeder
{
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (!$sulbar) return;

        $polresMap = Polres::where('polda_id', $sulbar->id)->pluck('id', 'code')->toArray();

        $personnel = [
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => null,
                'name'         => 'AKP Syamsuddin, S.H.',
                'username'     => 'kasubbid_penmas',
                'email'        => 'penmas.sulbar@polri.go.id',
                'password'     => Hash::make('PenmasSulbar#2026'),
                'role'         => 'verifikator',
                'badge_number' => '81050412',
                'phone'        => '081234567801',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => null,
                'name'         => 'Ipda Muhammad Ridwan, S.Kom',
                'username'     => 'analis_siber',
                'email'        => 'analis.sulbar@polri.go.id',
                'password'     => Hash::make('AnalisSulbar#2026'),
                'role'         => 'analis',
                'badge_number' => '89110255',
                'phone'        => '081234567802',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => $polresMap['MAMUJU'] ?? null,
                'name'         => 'Aipda Hendra Gunawan',
                'username'     => 'humas_mamuju',
                'email'        => 'humas.mamuju@polri.go.id',
                'password'     => Hash::make('PolresMamuju#2026'),
                'role'         => 'operator',
                'badge_number' => '85030877',
                'phone'        => '081234567803',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => $polresMap['MAJENE'] ?? null,
                'name'         => 'Bripka Ramli Pratama',
                'username'     => 'humas_majene',
                'email'        => 'humas.majene@polri.go.id',
                'password'     => Hash::make('PolresMajene#2026'),
                'role'         => 'operator',
                'badge_number' => '88070521',
                'phone'        => '081234567804',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => $polresMap['POLMAN'] ?? null,
                'name'         => 'Ipda Muh. Firdaus, S.H.',
                'username'     => 'humas_polman',
                'email'        => 'humas.polman@polri.go.id',
                'password'     => Hash::make('PolresPolman#2026'),
                'role'         => 'operator',
                'badge_number' => '84120339',
                'phone'        => '081234567805',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => $polresMap['MAMASA'] ?? null,
                'name'         => 'Bripka Daniel Tandi',
                'username'     => 'humas_mamasa',
                'email'        => 'humas.mamasa@polri.go.id',
                'password'     => Hash::make('PolresMamasa#2026'),
                'role'         => 'operator',
                'badge_number' => '90040118',
                'phone'        => '081234567806',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => $polresMap['PASANGKAYU'] ?? null,
                'name'         => 'Aipda Usman Kadir',
                'username'     => 'humas_pasangkayu',
                'email'        => 'humas.pasangkayu@polri.go.id',
                'password'     => Hash::make('PolresPasangkayu#2026'),
                'role'         => 'operator',
                'badge_number' => '86090744',
                'phone'        => '081234567807',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => $polresMap['MATENG'] ?? null,
                'name'         => 'Briptu Akbar Halim',
                'username'     => 'humas_mateng',
                'email'        => 'humas.mateng@polri.go.id',
                'password'     => Hash::make('PolresMateng#2026'),
                'role'         => 'operator',
                'badge_number' => '94030612',
                'phone'        => '081234567808',
                'is_active'    => true,
            ],
            [
                'polda_id'     => $sulbar->id,
                'polres_id'    => null,
                'name'         => 'Briptu Siti Nurhaliza',
                'username'     => 'operator_multimedia',
                'email'        => 'multimedia.sulbar@polri.go.id',
                'password'     => Hash::make('MultimediaSulbar#2026'),
                'role'         => 'operator',
                'badge_number' => '96040811',
                'phone'        => '081234567809',
                'is_active'    => true,
            ],
        ];

        foreach ($personnel as $p) {
            User::updateOrCreate(
                ['username' => $p['username']],
                $p
            );
        }
    }
}
