<?php
namespace Tests\Feature;

use App\Services\Pi\Development\NativePdfAnnexIntelligence;
use RuntimeException;
use Tests\TestCase;

final class R106PdfExtractorRecoveryTest extends TestCase
{
    public function test_native_command_on_linux_is_only_poppler_without_wsl(): void
    {
        $plans = (new NativePdfAnnexIntelligence)->extractorCommands('/tmp/example.pdf',null,'Linux');
        $this->assertCount(1,$plans);
        $this->assertSame('NATIVE_PATH_POPPLER',$plans[0]['label']);
    }

    public function test_windows_default_wsl_is_discovered_without_pinning_one_distro(): void
    {
        $plans = (new NativePdfAnnexIntelligence)->extractorCommands(
            'C:\Users\owner\Downloads\sample.pdf', null, 'Windows');
        $found = array_values(array_filter($plans,static fn($x)=>$x['label']==='WSL_DEFAULT'));
        $this->assertCount(1,$found);
        $this->assertSame(['wsl.exe','--exec','pdftotext'],
            array_slice($found[0]['argv'],0,3));
        $this->assertSame('/mnt/c/Users/owner/Downloads/sample.pdf',
            $found[0]['argv'][count($found[0]['argv'])-2]);
    }

    public function test_explicit_windows_poppler_path_is_single_candidate(): void
    {
        $plans = (new NativePdfAnnexIntelligence)->extractorCommands(
            'C:\some.pdf', 'C:\Program Files\Poppler\pdftotext.exe', 'Windows');
        $this->assertCount(1,$plans);
        $this->assertSame('EXPLICIT_POPPLER',$plans[0]['label']);
        $this->assertSame('C:\Program Files\Poppler\pdftotext.exe',$plans[0]['argv'][0]);
    }

    public function test_empty_extractor_is_never_executed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PI_R106_EXPLICIT_EXTRACTOR_UNSAFE');
        (new NativePdfAnnexIntelligence)->extractorCommands('C:\some.pdf',' ','Windows');
    }
}
