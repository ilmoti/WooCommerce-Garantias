<?php
// Necesitamos acceder a WordPress para obtener los motivos
define('WP_USE_THEMES', false);
require_once('../../../../wp-load.php');

// Obtener motivos de garantía
$motivos_txt = get_option('motivos_garantia', "Producto defectuoso\nFalla técnica\nFaltan piezas\nOtro");
$motivos = array_filter(array_map('trim', explode("\n", $motivos_txt)));

// Headers para Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=plantilla-garantias.xls");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
<table border="1">
<!-- Encabezados para orientar al usuario -->
<tr style="background-color: #17a2b8; color: white; font-weight: bold;">
    <td>SKU/Código</td>
    <td>Cantidad</td>
    <td>Motivo</td>
    <td style="background-color: #ffc107;">MOTIVOS VÁLIDOS (Referencia)</td>
</tr>
<!-- Ejemplos con motivos -->
<tr>
    <td>ABC123</td>
    <td>2</td>
    <td><?php echo $motivos[0] ?? 'Producto defectuoso'; ?></td>
    <td rowspan="10" style="background-color: #fff3cd; vertical-align: top;">
        <?php foreach($motivos as $motivo): ?>
        • <?php echo $motivo; ?><br>
        <?php endforeach; ?>
        <br>
        <strong>NOTA:</strong> Usa exactamente estos textos o será clasificado como "Otro"
    </td>
</tr>
<tr>
    <td>XYZ789</td>
    <td>1</td>
    <td><?php echo $motivos[1] ?? 'Falla técnica'; ?></td>
</tr>
<tr>
    <td>DEF456</td>
    <td>3</td>
    <td><?php echo $motivos[2] ?? 'Faltan piezas'; ?></td>
</tr>
<!-- Filas vacías para completar hasta 150 -->
<?php for($i = 4; $i <= 150; $i++): ?>
<tr style="background-color: <?php echo $i % 2 == 0 ? '#f8f9fa' : '#ffffff'; ?>;">
    <td></td>
    <td></td>
    <td></td>
</tr>
<?php endfor; ?>
</table>
</body>
</html>