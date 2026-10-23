<?php

namespace App\Controllers\Seguridad;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;

// CU01 boundary. Mensajes: redirigir(rol), mostrarMenuSegunRol().
// CU02: seleccionarCerrarSesion().
class UI_PanelPrincipal extends Controller
{
    public function redirigir(string $rol): void
    {
        $usuario = Session::user() ?? [];
        $usuario['rol'] = $rol;
        Session::set('usuario', $usuario);
        $this->redirect('/dashboard');
    }

    public function mostrarMenuSegunRol(): void
    {
        $usuario = Session::user() ?? [];
        $this->view('Seguridad/UI_PanelPrincipal', [
            'usuario' => $usuario,
            'menu'    => $this->menu($usuario),
        ]);
    }

    public function seleccionarCerrarSesion(): void
    {
        (new CtrlAutenticacion())->cerrarSesion();
    }

    private function menu(array $usuario): array
    {
        $rol = (string) ($usuario['rol'] ?? '');

        return match ($rol) {
            'Administrador' => $this->etiquetas(['Gestión humana', 'Auditoría', 'Configuración clínica']),
            'Recepcionista' => $this->etiquetas(['Agenda maestra', 'Sala de espera', 'Admisión y cobros']),
            'Médico'        => $this->etiquetas($this->modulosMedico((int) ($usuario['id_usuario'] ?? 0))),
            'Paciente'      => $this->etiquetas(['Citas', 'Resultados y recetas']),
            default         => [],
        };
    }

    private function etiquetas(array $textos): array
    {
        return array_map(static fn (string $texto): array => [
            'texto' => $texto,
            'ruta'  => null,
            'trazo' => 'M5 7h14M5 12h14M5 17h9',
        ], $textos);
    }

    private function modulosMedico(int $idUsuario): array
    {
        $medico = $this->fila(
            "SELECT COALESCE(string_agg(e.nombre, ' · ' ORDER BY e.nombre), '') AS especialidades
               FROM medico m
               LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
               LEFT JOIN especialidad e ON e.id_especialidad = me.id_especialidad
              WHERE m.id_usuario = :id
              GROUP BY m.id_medico",
            ['id' => $idUsuario]
        );
        $nombres = $medico['especialidades'] ?? '';
        foreach ([
            'Cardiología' => ['Signos vitales', 'Visor de estudios', 'Riesgo cardiovascular'],
            'Pediatría' => ['Curvas de crecimiento', 'Vacunación', 'Antecedentes perinatales'],
            'Ginecología' => ['Control prenatal', 'Historial obstétrico', 'Exámenes preventivos'],
            'Odontología' => ['Odontograma', 'Periodontograma', 'Planes de tratamiento'],
        ] as $especialidad => $modulos) {
            if (str_contains($nombres, $especialidad)) {
                return $modulos;
            }
        }

        return ['Historia clínica', 'Prescripciones', 'Derivaciones'];
    }

    private function fila(string $sql, array $params): ?array
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }
}
