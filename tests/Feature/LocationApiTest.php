<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_regiones()
    {
        Region::create(['idRegion' => '15', 'nombreRegion' => 'LIMA']);

        $this->getJson('/api/regiones')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.nombreRegion', 'LIMA');
    }

    public function test_lista_provincias_de_una_region()
    {
        Province::create(['idProvincia' => '1501', 'nombreProvincia' => 'LIMA']);
        Province::create(['idProvincia' => '1001', 'nombreProvincia' => 'HUANUCO']);

        $this->getJson('/api/provincias/15')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.idProvincia', '1501');

        $this->getJson('/api/provincias/99')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }

    public function test_lista_distritos_de_una_provincia()
    {
        District::create(['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']);
        District::create(['idDistrito' => '150201', 'nombreDistrito' => 'BARRANCA']);

        $this->getJson('/api/distritos/1501')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.idDistrito', '150101');

        $this->getJson('/api/distritos/9999')->assertStatus(404);
    }

    public function test_lista_colegios_de_un_distrito()
    {
        School::create(['codMod' => '0000001', 'idDistrito' => '150101', 'nombreCenEdu' => 'COLEGIO UNO']);

        $this->getJson('/api/colegios/150101')
            ->assertStatus(200)
            ->assertJsonPath('data.0.nombreCenEdu', 'COLEGIO UNO');
    }

    public function test_colegios_sin_resultados_devuelve_404()
    {
        $this->getJson('/api/colegios/000000')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }
}
