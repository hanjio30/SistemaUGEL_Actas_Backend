<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolicitanteController;
use App\Http\Controllers\AsuntoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HistorialExpedienteController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ConsultaController;

// =====================================================
// RUTAS DE CONSULTA PÚBLICA (Sin autenticación)
// ⚠️ IMPORTANTE: Estas rutas van PRIMERO porque son públicas
// =====================================================

// Consultar expediente por código de seguimiento (firma de ruta)
Route::get('/consulta/expediente/{firma_ruta}', [ConsultaController::class, 'consultarPorFirmaRuta']);

// Verificar disponibilidad del servicio
Route::get('/consulta/health', [ConsultaController::class, 'verificarServicio']);

// Estadísticas públicas (opcional)
Route::get('/consulta/estadisticas', [ConsultaController::class, 'estadisticasPublicas']);

// Búsqueda avanzada (opcional)
Route::post('/consulta/busqueda-avanzada', [ConsultaController::class, 'busquedaAvanzada']);


// =====================================================
// RUTAS DE AUTENTICACIÓN
// =====================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/usuario-actual', [AuthController::class, 'usuarioActual']);


// =====================================================
// RUTAS DE DOCUMENTOS
// =====================================================
Route::get('/documentos', [DocumentoController::class, 'index']);
Route::get('/documentos/{id}', [DocumentoController::class, 'show']);


// =====================================================
// RUTAS DE ASUNTOS
// =====================================================
Route::get('/asuntos', [AsuntoController::class, 'index']); // Filtrar por documento_id
Route::get('/asuntos/{id}', [AsuntoController::class, 'show']);
Route::post('/asuntos', [AsuntoController::class, 'store']);
Route::put('/asuntos/{id}', [AsuntoController::class, 'update']);
Route::delete('/asuntos/{id}', [AsuntoController::class, 'destroy']);


// =====================================================
// RUTAS DE SOLICITANTES
// =====================================================
Route::get('/solicitantes', [SolicitanteController::class, 'index']); // Buscar por DNI o Código
Route::get('/solicitantes/{id}', [SolicitanteController::class, 'show']);
Route::post('/solicitantes', [SolicitanteController::class, 'store']);
Route::put('/solicitantes/{id}', [SolicitanteController::class, 'update']);
Route::delete('/solicitantes/{id}', [SolicitanteController::class, 'destroy']);


// =====================================================
// RUTAS DE EXPEDIENTES
// ⚠️ IMPORTANTE: Las rutas específicas DEBEN ir ANTES de las rutas con parámetros {id}
// =====================================================

// 1. Rutas específicas primero (sin parámetros o con parámetros específicos)
Route::get('/expedientes/exportar-observados', [ExpedienteController::class, 'exportarObservados']);
Route::get('/expedientes/seguimiento/{firma_ruta}', [ExpedienteController::class, 'buscarPorFirmaRuta']);

// 2. Rutas generales con parámetro {id} al final
Route::get('/expedientes', [ExpedienteController::class, 'index']);
Route::get('/expedientes/{id}', [ExpedienteController::class, 'show']);
Route::post('/expedientes', [ExpedienteController::class, 'store']);
Route::put('/expedientes/{id}', [ExpedienteController::class, 'update']);
Route::delete('/expedientes/{id}', [ExpedienteController::class, 'destroy']);


// =====================================================
// RUTAS DE HISTORIAL DE EXPEDIENTES
// =====================================================
Route::get('/expedientes/{expediente_id}/historial', [HistorialExpedienteController::class, 'index']);
Route::post('/historial', [HistorialExpedienteController::class, 'store']);


// =====================================================
// RUTAS DE ATENCIONES
// =====================================================
Route::post('/atenciones', [AtencionController::class, 'store']);
Route::get('/atenciones', [AtencionController::class, 'index']);
Route::get('/expedientes/{id_expediente}/historial-atenciones', [AtencionController::class, 'historial']);
Route::get('/estadisticas-atenciones', [AtencionController::class, 'estadisticasPorUsuario']);


// =====================================================
// RUTAS DE NOTIFICACIONES
// =====================================================
Route::post('/notificaciones/vencimientos', [NotificacionController::class, 'notificarVencimientos']);
Route::get('/notificaciones/estadisticas-observados', [NotificacionController::class, 'estadisticasObservados']);
Route::get('/notificaciones/destinatarios', [NotificacionController::class, 'obtenerDestinatarios']);

// =====================================================
// RUTAS DE ENTREGAS
// =====================================================
// Rutas específicas primero
Route::get('/entregas/estadisticas', [EntregaController::class, 'estadisticas']);
Route::get('/entregas/expediente/{expediente_id}', [EntregaController::class, 'porExpediente']);
Route::get('/entregas/{id}/autorizacion', [EntregaController::class, 'descargarAutorizacion']);

// Rutas CRUD estándar
Route::get('/entregas', [EntregaController::class, 'index']);
Route::get('/entregas/{id}', [EntregaController::class, 'show']);
Route::post('/entregas', [EntregaController::class, 'store']);
Route::delete('/entregas/{id}', [EntregaController::class, 'destroy']);

// =====================================================
// RUTAS DE USUARIOS
// ⚠️ IMPORTANTE: Las rutas específicas DEBEN ir ANTES de las rutas con parámetros {id}
// =====================================================

// 1. Rutas específicas primero
Route::get('/usuarios/estadisticas', [UsuarioController::class, 'estadisticas']);
Route::get('/usuarios/buscar-dni/{dni}', [UsuarioController::class, 'buscarPorDni']);

// 2. Rutas con acciones específicas en recursos
Route::patch('/usuarios/{id}/estado', [UsuarioController::class, 'cambiarEstado']);
Route::post('/usuarios/{id}/actualizar-acceso', [UsuarioController::class, 'actualizarUltimoAcceso']);

// 3. Rutas CRUD estándar (estas van al final)
Route::get('/usuarios', [UsuarioController::class, 'index']);
Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);
Route::post('/usuarios', [UsuarioController::class, 'store']);
Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);


// =====================================================
// RUTAS DE REPORTES Y ESTADÍSTICAS
// =====================================================

// Reportes generales
Route::get('/reportes/expedientes-periodo', [ReporteController::class, 'expedientesPorPeriodo']);
Route::get('/reportes/estados-actuales', [ReporteController::class, 'estadosActuales']);
Route::get('/reportes/por-colaborador', [ReporteController::class, 'porColaborador']);
Route::get('/reportes/tiempos-atencion', [ReporteController::class, 'tiemposAtencion']);
Route::get('/reportes/expedientes-observados', [ReporteController::class, 'expedientesObservados']);
Route::get('/reportes/entregas', [ReporteController::class, 'reporteEntregas']);

// Exportación
Route::post('/reportes/exportar-excel', [ReporteController::class, 'exportarExcel']);
