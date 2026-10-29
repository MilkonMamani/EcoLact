# Abre el DOCX en Microsoft Word, actualiza índice y campos (figuras, páginas), guarda y exporta a PDF.
# Registra cada etapa en salida/../evidencias/word_a_pdf.log para diagnosticar bloqueos.
param([Parameter(Mandatory = $true)][string]$Docx)
$ErrorActionPreference = 'Stop'
$docx = (Resolve-Path $Docx).Path
$pdf = [System.IO.Path]::ChangeExtension($docx, '.pdf')
$log = Join-Path (Split-Path (Split-Path $docx)) 'evidencias\word_a_pdf.log'
function Paso($t) { "$(Get-Date -Format 'HH:mm:ss') $t" | Out-File -FilePath $log -Append -Encoding utf8 }
"" | Out-File -FilePath $log -Encoding utf8
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$word.Options.CheckGrammarAsYouType = $false
$word.Options.CheckSpellingAsYouType = $false
try {
    # Se trabaja sobre una copia con nombre único: si Word marcó el archivo maestro tras un cierre
    # forzado («provocó un error grave…»), ese aviso bloquearía la automatización invisible.
    $tmp = Join-Path $env:TEMP ('ecolact_manual_' + [guid]::NewGuid().ToString('N') + '.docx')
    Copy-Item $docx $tmp
    Paso 'abrir'
    # Open(FileName, ConfirmConversions, ReadOnly, AddToRecentFiles)
    $doc = $word.Documents.Open($tmp, $false, $false, $false)
    Paso 'campos'
    $doc.Fields.Update() | Out-Null
    Paso 'indice'
    foreach ($toc in $doc.TablesOfContents) { $toc.Update() | Out-Null }
    Paso 'repaginar'
    $doc.Repaginate()
    foreach ($toc in $doc.TablesOfContents) { $toc.UpdatePageNumbers() | Out-Null }
    Paso 'guardar'
    $doc.Save()
    Paso 'pdf'
    # 17 = wdFormatPDF; marcadores desde títulos para navegar el PDF.
    $pdfTmp = [System.IO.Path]::ChangeExtension($tmp, '.pdf')
    $doc.ExportAsFixedFormat($pdfTmp, 17, $false, 0, 0, 1, 1, 0, $true, $true, 1, $true, $true, $false)
    Paso 'cerrar'
    $doc.Close($false)
    Copy-Item $tmp $docx -Force
    Remove-Item $tmp -Force
    try {
        Copy-Item $pdfTmp $pdf -Force -ErrorAction Stop
        Write-Host "PDF: $pdf"
    } catch {
        # El PDF de salida está abierto en otro programa (p. ej. Acrobat): se deja la versión nueva aparte.
        $pendiente = Join-Path (Split-Path (Split-Path $docx)) 'evidencias\Manual_PDF_pendiente_de_copiar.pdf'
        Copy-Item $pdfTmp $pendiente -Force
        Write-Host "AVISO: $pdf está abierto en otro programa. PDF nuevo en: $pendiente"
        Paso 'pdf bloqueado'
    }
    Remove-Item $pdfTmp -Force
    Paso 'fin'
}
finally {
    $word.Quit()
}
