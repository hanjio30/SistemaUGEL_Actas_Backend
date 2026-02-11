<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expedientes Próximos a Vencer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #f59e0b;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .alert-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .user-info {
            background-color: #eff6ff;
            border-left: 4px solid #083f8f;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .expediente-card {
            background-color: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .expediente-card.urgente {
            border-color: #dc2626;
            background-color: #fee2e2;
        }
        .expediente-card.advertencia {
            border-color: #f59e0b;
            background-color: #fef3c7;
        }
        .expediente-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .expediente-num {
            color: #083f8f;
            font-size: 16px;
        }
        .dias-badge {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }
        .dias-badge.urgente {
            background-color: #dc2626;
            color: white;
        }
        .dias-badge.advertencia {
            background-color: #f59e0b;
            color: white;
        }
        .expediente-detail {
            margin: 8px 0;
            font-size: 14px;
        }
        .expediente-detail strong {
            color: #374151;
        }
        .observacion {
            background-color: #ffffff;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            border-left: 3px solid #f59e0b;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #083f8f;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .role-admin {
            background-color: #dc2626;
            color: white;
        }
        .role-colaborador {
            background-color: #0ea5d7;
            color: white;
        }
        .stats {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #dc2626;
        }
        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="alert-icon">⚠️</div>
            <h1>ALERTA: Expedientes Observados</h1>
            <p style="margin: 5px 0 0 0; font-size: 14px;">Próximos a Vencer</p>
        </div>

        <div class="user-info">
            <strong>👤 Destinatario:</strong> {{ $usuario }}
            @if(isset($rol))
                <span class="role-badge {{ strtolower($rol) == 'administrador' ? 'role-admin' : 'role-colaborador' }}">
                    {{ $rol }}
                </span>
            @endif
        </div>

        <p>Estimado/a <strong>{{ $usuario }}</strong>,</p>

        <p>Se le notifica que existen expedientes observados que están próximos a cumplir el plazo máximo de 10 días.</p>

        <div class="stats">
            <div class="stat-number">{{ $cantidad }}</div>
            <div class="stat-label">Expediente{{ $cantidad != 1 ? 's' : '' }} requiere{{ $cantidad != 1 ? 'n' : '' }} atención</div>
        </div>

        <div class="info-box">
            <strong>📋 Resumen de Expedientes:</strong> A continuación se detallan los expedientes que requieren atención urgente.
        </div>

        @foreach($expedientes as $exp)
            <div class="expediente-card {{ $exp['dias'] >= 10 ? 'urgente' : 'advertencia' }}">
                <div class="expediente-header">
                    <span class="expediente-num">📄 {{ $exp['num_expediente'] }}</span>
                    <span class="dias-badge {{ $exp['dias'] >= 10 ? 'urgente' : 'advertencia' }}">
                        {{ $exp['dias'] }}/10 días
                    </span>
                </div>
                
                @if(!empty($exp['solicitante']))
                <div class="expediente-detail">
                    <strong>👤 Solicitante:</strong> {{ $exp['solicitante'] }}
                </div>
                @endif
                
                @if(!empty($exp['observaciones']))
                <div class="observacion">
                    <strong>📝 Observación:</strong><br>
                    {{ $exp['observaciones'] }}
                </div>
                @endif
            </div>
        @endforeach

        <div class="info-box">
            <strong>⏰ Acciones Requeridas:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Revisar cada expediente observado</li>
                <li>Coordinar con los solicitantes para subsanar las observaciones</li>
                @if(isset($rol) && strtolower($rol) == 'administrador')
                <li>Corregir el estado de los expedientes una vez resueltas las observaciones</li>
                <li>Delegar tareas a colaboradores según sea necesario</li>
                @else
                <li>Informar al administrador sobre el avance de las correcciones</li>
                <li>Contactar a los solicitantes para completar documentación</li>
                @endif
                <li><strong>Importante:</strong> Los expedientes con 10 o más días son considerados VENCIDOS</li>
            </ul>
        </div>

        <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <strong style="color: #dc2626;">🚨 Recordatorio Importante:</strong>
            <p style="margin: 10px 0 0 0; color: #7f1d1d;">
                El plazo máximo para expedientes observados es de <strong>10 días hábiles</strong>. 
                Una vez cumplido este plazo, se considera que el expediente está vencido y podría 
                requerir acciones administrativas adicionales.
            </p>
        </div>

        <div class="footer">
            <p><strong>Sistema de Gestión de Expedientes - UGEL</strong></p>
            <p>Notificación automática enviada el {{ $fecha }}</p>
            <p style="margin-top: 15px; font-size: 12px;">
                Esta notificación se envió a <strong>todos los usuarios</strong> del sistema 
                (Administradores y Colaboradores) para mantenerlos informados sobre el 
                estado de los expedientes observados.
            </p>
            <p style="font-size: 11px; color: #9ca3af; margin-top: 10px;">
                Este es un correo automático. Por favor no responder a esta dirección.<br>
                Para consultas, contacte al administrador del sistema.
            </p>
        </div>
    </div>
</body>
</html>