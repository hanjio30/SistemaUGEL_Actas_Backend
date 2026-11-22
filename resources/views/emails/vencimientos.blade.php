<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Expedientes observados próximos a vencer</title>
</head>
@php
    $lista = collect($expedientes)->sortByDesc('dias')->values();
    $totalVencidos = $lista->where('dias', '>=', 10)->count();
    $totalPorVencer = $lista->count() - $totalVencidos;
    $esAdmin = in_array(strtolower($rol ?? ''), ['admin', 'administrador']);
    $urlSistema = 'https://sistema-ugel-actas-fronted.vercel.app/';
@endphp
<body style="margin:0; padding:0; background-color:#eef1f6; font-family:'Segoe UI', Helvetica, Arial, sans-serif; color:#1f2937;">

    {{-- Texto de vista previa (se ve en la bandeja de entrada) --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#eef1f6;">
        {{ $cantidad }} expediente(s) observado(s) requieren atención antes de cumplir el plazo de 10 días hábiles.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef1f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:640px;">

                    {{-- Encabezado institucional --}}
                    <tr>
                        <td style="background-color:#083f8f; padding:28px 36px; border-radius:10px 10px 0 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size:13px; letter-spacing:2px; text-transform:uppercase; color:#b6c8ea; font-weight:600;">
                                        UGEL &middot; Sistema de Gestión de Expedientes
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:10px; font-size:24px; line-height:1.3; font-weight:700; color:#ffffff;">
                                        Alerta de plazos: expedientes observados
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f59e0b; height:4px; line-height:4px; font-size:0;">&nbsp;</td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="background-color:#ffffff; padding:36px;">

                            <p style="margin:0 0 6px 0; font-size:16px; color:#111827;">
                                Estimado/a <strong>{{ $usuario }}</strong>,
                            </p>
                            <p style="margin:0 0 4px 0; font-size:12px; color:#6b7280;">
                                {{ $esAdmin ? 'Administrador' : 'Colaborador' }} &middot; {{ $fecha }}
                            </p>

                            <p style="margin:22px 0 28px 0; font-size:15px; line-height:1.7; color:#374151;">
                                Le informamos que hay expedientes en estado <strong>OBSERVADO</strong> que están
                                próximos a cumplir, o ya cumplieron, el plazo máximo de
                                <strong>10 días hábiles</strong>. Se requiere su atención.
                            </p>

                            {{-- Resumen --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:32px;">
                                <tr>
                                    <td width="33%" align="center" style="background-color:#f3f6fb; border:1px solid #dbe3f0; border-radius:8px; padding:18px 8px;">
                                        <div style="font-size:30px; font-weight:700; color:#083f8f; line-height:1;">{{ $cantidad }}</div>
                                        <div style="font-size:12px; color:#6b7280; margin-top:6px; text-transform:uppercase; letter-spacing:0.5px;">Total</div>
                                    </td>
                                    <td width="2%">&nbsp;</td>
                                    <td width="30%" align="center" style="background-color:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:18px 8px;">
                                        <div style="font-size:30px; font-weight:700; color:#b45309; line-height:1;">{{ $totalPorVencer }}</div>
                                        <div style="font-size:12px; color:#92400e; margin-top:6px; text-transform:uppercase; letter-spacing:0.5px;">Por vencer</div>
                                    </td>
                                    <td width="2%">&nbsp;</td>
                                    <td width="30%" align="center" style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:18px 8px;">
                                        <div style="font-size:30px; font-weight:700; color:#b91c1c; line-height:1;">{{ $totalVencidos }}</div>
                                        <div style="font-size:12px; color:#991b1b; margin-top:6px; text-transform:uppercase; letter-spacing:0.5px;">Vencidos</div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Detalle --}}
                            <p style="margin:0 0 12px 0; font-size:13px; font-weight:700; color:#083f8f; text-transform:uppercase; letter-spacing:1px;">
                                Detalle de expedientes
                            </p>

                            @foreach($lista as $exp)
                                @php
                                    $dias = (int) ($exp['dias'] ?? 0);
                                    $vencido = $dias >= 10;
                                    $color = $vencido ? '#b91c1c' : '#b45309';
                                    $fondoBadge = $vencido ? '#fee2e2' : '#fef3c7';
                                    $bordeIzq = $vencido ? '#dc2626' : '#f59e0b';
                                    $porcentaje = min(100, $dias * 10);
                                @endphp
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px; border:1px solid #e5e7eb; border-left:4px solid {{ $bordeIzq }}; border-radius:6px;">
                                    <tr>
                                        <td style="padding:16px 18px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="font-size:16px; font-weight:700; color:#083f8f;">
                                                        N.° {{ $exp['num_expediente'] }}
                                                    </td>
                                                    <td align="right">
                                                        <span style="display:inline-block; background-color:{{ $fondoBadge }}; color:{{ $color }}; font-size:12px; font-weight:700; padding:5px 12px; border-radius:20px;">
                                                            {{ $vencido ? 'VENCIDO' : 'POR VENCER' }} &middot; {{ $dias }}/10 días
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>

                                            {{-- Barra de progreso del plazo --}}
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0;">
                                                <tr>
                                                    <td style="background-color:#e5e7eb; border-radius:4px; height:6px; line-height:6px; font-size:0;">
                                                        <table role="presentation" width="{{ $porcentaje }}%" cellpadding="0" cellspacing="0" border="0">
                                                            <tr>
                                                                <td style="background-color:{{ $bordeIzq }}; border-radius:4px; height:6px; line-height:6px; font-size:0;">&nbsp;</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>

                                            @if(!empty($exp['solicitante']))
                                                <p style="margin:0 0 6px 0; font-size:14px; color:#374151;">
                                                    <span style="color:#6b7280;">Solicitante:</span>
                                                    <strong>{{ $exp['solicitante'] }}</strong>
                                                </p>
                                            @endif

                                            @if(!empty($exp['observaciones']))
                                                <p style="margin:0; font-size:13px; line-height:1.6; color:#4b5563; background-color:#f9fafb; padding:10px 12px; border-radius:4px;">
                                                    <span style="color:#6b7280;">Observación:</span>
                                                    {{ $exp['observaciones'] }}
                                                </p>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            @endforeach

                            {{-- Acciones requeridas --}}
                            <p style="margin:30px 0 10px 0; font-size:13px; font-weight:700; color:#083f8f; text-transform:uppercase; letter-spacing:1px;">
                                Acciones requeridas
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; line-height:1.7; color:#374151;">
                                <tr>
                                    <td width="24" valign="top" style="color:#083f8f; font-weight:700;">1.</td>
                                    <td style="padding-bottom:6px;">Revisar cada expediente observado y su detalle.</td>
                                </tr>
                                <tr>
                                    <td valign="top" style="color:#083f8f; font-weight:700;">2.</td>
                                    <td style="padding-bottom:6px;">Coordinar con los solicitantes para subsanar las observaciones.</td>
                                </tr>
                                @if($esAdmin)
                                    <tr>
                                        <td valign="top" style="color:#083f8f; font-weight:700;">3.</td>
                                        <td style="padding-bottom:6px;">Corregir el estado de los expedientes una vez resueltas las observaciones.</td>
                                    </tr>
                                    <tr>
                                        <td valign="top" style="color:#083f8f; font-weight:700;">4.</td>
                                        <td>Delegar tareas a los colaboradores según sea necesario.</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td valign="top" style="color:#083f8f; font-weight:700;">3.</td>
                                        <td style="padding-bottom:6px;">Contactar a los solicitantes para completar la documentación.</td>
                                    </tr>
                                    <tr>
                                        <td valign="top" style="color:#083f8f; font-weight:700;">4.</td>
                                        <td>Informar al administrador sobre el avance de las correcciones.</td>
                                    </tr>
                                @endif
                            </table>

                            {{-- Botón --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:34px auto 8px auto;">
                                <tr>
                                    <td align="center" style="background-color:#083f8f; border-radius:6px;">
                                        <a href="{{ $urlSistema }}" target="_blank" style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none;">
                                            Ir al sistema
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Aviso de plazo --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
                                <tr>
                                    <td style="background-color:#fef2f2; border-left:4px solid #dc2626; border-radius:4px; padding:14px 18px; font-size:13px; line-height:1.7; color:#7f1d1d;">
                                        <strong>Recordatorio:</strong> el plazo máximo para expedientes observados es de
                                        <strong>10 días hábiles</strong>. Cumplido ese plazo, el expediente se considera
                                        vencido y podría requerir acciones administrativas adicionales.
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color:#f8fafc; border-top:1px solid #e5e7eb; padding:24px 36px; border-radius:0 0 10px 10px; text-align:center;">
                            <p style="margin:0 0 6px 0; font-size:13px; font-weight:700; color:#083f8f;">
                                Sistema de Gestión de Expedientes &ndash; UGEL
                            </p>
                            <p style="margin:0 0 10px 0; font-size:12px; color:#6b7280;">
                                Notificación automática generada el {{ $fecha }}
                            </p>
                            <p style="margin:0; font-size:11px; line-height:1.6; color:#9ca3af;">
                                Este es un mensaje automático, por favor no responda a esta dirección.<br>
                                Para consultas, comuníquese con el administrador del sistema.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>