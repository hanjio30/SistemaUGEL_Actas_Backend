<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UsuarioController extends Controller
{
    /**
     * Listar todos los usuarios con filtros opcionales
     * GET /api/usuarios?estado=Activo&rol=Colaborador&buscar=juan
     */
    public function index(Request $request)
    {
        try {
            $query = Usuario::query();

            // Filtro por estado
            if ($request->has('estado') && in_array($request->estado, ['Activo', 'Inactivo'])) {
                $query->where('estado', $request->estado);
            }

            // Filtro por rol
            if ($request->has('rol') && in_array($request->rol, ['Administrador', 'Colaborador'])) {
                $query->where('rol', $request->rol);
            }

            // Búsqueda por nombre, usuario, DNI o correo
            if ($request->has('buscar') && !empty($request->buscar)) {
                $buscar = $request->buscar;
                $query->where(function($q) use ($buscar) {
                    $q->where('nombre_completo', 'LIKE', "%{$buscar}%")
                      ->orWhere('usuario', 'LIKE', "%{$buscar}%")
                      ->orWhere('dni', 'LIKE', "%{$buscar}%")
                      ->orWhere('correo', 'LIKE', "%{$buscar}%");
                });
            }

            $usuarios = $query->select(
                'id', 
                'nombre_completo', 
                'dni', 
                'telefono',
                'usuario', 
                'correo', 
                'rol', 
                'estado',
                'foto_perfil',
                'ultimo_acceso',
                'created_at',
                'updated_at'
            )
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json($usuarios, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un usuario específico
     * GET /api/usuarios/{id}
     */
    public function show($id)
    {
        try {
            $usuario = Usuario::select(
                'id', 
                'nombre_completo', 
                'dni', 
                'telefono',
                'usuario', 
                'correo', 
                'rol', 
                'estado',
                'foto_perfil',
                'ultimo_acceso',
                'created_at',
                'updated_at'
            )->findOrFail($id);

            // Agregar información adicional
            $usuario->total_atenciones = $usuario->expedientesAtendidos()->count();
            $usuario->total_entregas = $usuario->entregas()->count();
            $usuario->notificaciones_pendientes = $usuario->contarNoLeidas();

            return response()->json($usuario, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Usuario no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear un nuevo usuario
     * POST /api/usuarios
     */
    public function store(Request $request)
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'nombre_completo' => 'required|string|max:255',
            'dni' => 'required|string|size:8|unique:usuario,dni',
            'telefono' => 'nullable|string|max:15',
            'usuario' => 'required|string|min:3|max:100|unique:usuario,usuario',
            'contrasena' => 'required|string|min:6|max:255',
            'correo' => 'required|email|max:255|unique:usuario,correo',
            'rol' => 'required|in:Administrador,Colaborador',
            'estado' => 'nullable|in:Activo,Inactivo',
            'foto_perfil' => 'nullable|image|mimes:jpeg,jpg,png|max:2048' // 2MB máximo
        ], [
            'nombre_completo.required' => 'El nombre completo es obligatorio',
            'dni.required' => 'El DNI es obligatorio',
            'dni.size' => 'El DNI debe tener 8 dígitos',
            'dni.unique' => 'Este DNI ya está registrado',
            'usuario.required' => 'El usuario es obligatorio',
            'usuario.min' => 'El usuario debe tener al menos 3 caracteres',
            'usuario.unique' => 'Este usuario ya existe',
            'contrasena.required' => 'La contraseña es obligatoria',
            'contrasena.min' => 'La contraseña debe tener al menos 6 caracteres',
            'correo.required' => 'El correo es obligatorio',
            'correo.email' => 'El correo no es válido',
            'correo.unique' => 'Este correo ya está registrado',
            'rol.required' => 'El rol es obligatorio',
            'rol.in' => 'El rol debe ser Administrador o Colaborador',
            'foto_perfil.image' => 'El archivo debe ser una imagen',
            'foto_perfil.mimes' => 'La imagen debe ser JPG, JPEG o PNG',
            'foto_perfil.max' => 'La imagen no debe superar 2MB'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Procesar foto de perfil si existe
            $fotoPerfil = null;
            if ($request->hasFile('foto_perfil')) {
                $fotoPerfil = $request->file('foto_perfil')->store('usuarios/fotos', 'public');
            }

            // Crear usuario
            $usuario = Usuario::create([
                'nombre_completo' => $request->nombre_completo,
                'dni' => $request->dni,
                'telefono' => $request->telefono,
                'usuario' => $request->usuario,
                'contrasena' => $request->contrasena, // El mutador lo hasheará automáticamente
                'correo' => $request->correo,
                'rol' => $request->rol,
                'estado' => $request->estado ?? 'Activo',
                'foto_perfil' => $fotoPerfil
            ]);

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => [
                    'id' => $usuario->id,
                    'nombre_completo' => $usuario->nombre_completo,
                    'dni' => $usuario->dni,
                    'telefono' => $usuario->telefono,
                    'usuario' => $usuario->usuario,
                    'correo' => $usuario->correo,
                    'rol' => $usuario->rol,
                    'estado' => $usuario->estado,
                    'foto_perfil' => $usuario->foto_perfil
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un usuario
     * PUT /api/usuarios/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            // Validación
            $validator = Validator::make($request->all(), [
                'nombre_completo' => 'required|string|max:255',
                'dni' => [
                    'required',
                    'string',
                    'size:8',
                    Rule::unique('usuario', 'dni')->ignore($id)
                ],
                'telefono' => 'nullable|string|max:15',
                'usuario' => [
                    'required',
                    'string',
                    'min:3',
                    'max:100',
                    Rule::unique('usuario', 'usuario')->ignore($id)
                ],
                'correo' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('usuario', 'correo')->ignore($id)
                ],
                'rol' => 'required|in:Administrador,Colaborador',
                'estado' => 'required|in:Activo,Inactivo',
                'contrasena' => 'nullable|string|min:6|max:255',
                'foto_perfil' => 'nullable|image|mimes:jpeg,jpg,png|max:2048'
            ], [
                'nombre_completo.required' => 'El nombre completo es obligatorio',
                'dni.required' => 'El DNI es obligatorio',
                'dni.size' => 'El DNI debe tener 8 dígitos',
                'dni.unique' => 'Este DNI ya está registrado',
                'usuario.required' => 'El usuario es obligatorio',
                'usuario.min' => 'El usuario debe tener al menos 3 caracteres',
                'usuario.unique' => 'Este usuario ya existe',
                'correo.required' => 'El correo es obligatorio',
                'correo.email' => 'El correo no es válido',
                'correo.unique' => 'Este correo ya está registrado',
                'rol.required' => 'El rol es obligatorio',
                'estado.required' => 'El estado es obligatorio',
                'contrasena.min' => 'La contraseña debe tener al menos 6 caracteres'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar datos básicos
            $usuario->nombre_completo = $request->nombre_completo;
            $usuario->dni = $request->dni;
            $usuario->telefono = $request->telefono;
            $usuario->usuario = $request->usuario;
            $usuario->correo = $request->correo;
            $usuario->rol = $request->rol;
            $usuario->estado = $request->estado;

            // Actualizar contraseña si se proporciona
            if ($request->filled('contrasena')) {
                $usuario->contrasena = $request->contrasena;
            }

            // Actualizar foto de perfil si se proporciona
            if ($request->hasFile('foto_perfil')) {
                // Eliminar foto anterior si existe
                if ($usuario->foto_perfil && Storage::disk('public')->exists($usuario->foto_perfil)) {
                    Storage::disk('public')->delete($usuario->foto_perfil);
                }
                
                $usuario->foto_perfil = $request->file('foto_perfil')->store('usuarios/fotos', 'public');
            }

            $usuario->save();

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'data' => [
                    'id' => $usuario->id,
                    'nombre_completo' => $usuario->nombre_completo,
                    'dni' => $usuario->dni,
                    'telefono' => $usuario->telefono,
                    'usuario' => $usuario->usuario,
                    'correo' => $usuario->correo,
                    'rol' => $usuario->rol,
                    'estado' => $usuario->estado,
                    'foto_perfil' => $usuario->foto_perfil
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un usuario
     * DELETE /api/usuarios/{id}
     */
    public function destroy($id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            // Verificar si el usuario tiene expedientes atendidos
            if ($usuario->expedientesAtendidos()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el usuario porque tiene expedientes atendidos asociados'
                ], 400);
            }

            // Verificar si el usuario tiene entregas registradas
            if ($usuario->entregas()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el usuario porque tiene entregas registradas'
                ], 400);
            }

            // Eliminar foto de perfil si existe
            if ($usuario->foto_perfil && Storage::disk('public')->exists($usuario->foto_perfil)) {
                Storage::disk('public')->delete($usuario->foto_perfil);
            }

            $usuario->delete();

            return response()->json([
                'message' => 'Usuario eliminado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado de un usuario (Activar/Desactivar)
     * PATCH /api/usuarios/{id}/estado
     */
    public function cambiarEstado(Request $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'estado' => 'required|in:Activo,Inactivo'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Estado no válido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $usuario->estado = $request->estado;
            $usuario->save();

            return response()->json([
                'message' => "Usuario {$request->estado} exitosamente",
                'data' => [
                    'id' => $usuario->id,
                    'usuario' => $usuario->usuario,
                    'estado' => $usuario->estado
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de usuarios
     * GET /api/usuarios/estadisticas
     */
    public function estadisticas()
    {
        try {
            $total = Usuario::count();
            $activos = Usuario::where('estado', 'Activo')->count();
            $inactivos = Usuario::where('estado', 'Inactivo')->count();
            $administradores = Usuario::where('rol', 'Administrador')->count();
            $colaboradores = Usuario::where('rol', 'Colaborador')->count();
            
            // Usuarios más activos (los que más expedientes han atendido)
            $masActivos = Usuario::withCount('expedientesAtendidos')
                ->orderBy('expedientes_atendidos_count', 'desc')
                ->take(5)
                ->get(['id', 'nombre_completo', 'usuario']);

            return response()->json([
                'total' => $total,
                'activos' => $activos,
                'inactivos' => $inactivos,
                'administradores' => $administradores,
                'colaboradores' => $colaboradores,
                'usuarios_mas_activos' => $masActivos
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar usuario por DNI
     * GET /api/usuarios/buscar-dni/{dni}
     */
    public function buscarPorDni($dni)
    {
        try {
            $usuario = Usuario::where('dni', $dni)
                ->select('id', 'nombre_completo', 'dni', 'telefono', 'usuario', 'correo', 'rol', 'estado')
                ->first();

            if (!$usuario) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json($usuario, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar último acceso del usuario
     * POST /api/usuarios/{id}/actualizar-acceso
     */
    public function actualizarUltimoAcceso($id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $usuario->actualizarUltimoAcceso();

            return response()->json([
                'message' => 'Último acceso actualizado',
                'ultimo_acceso' => $usuario->ultimo_acceso
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar último acceso',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}