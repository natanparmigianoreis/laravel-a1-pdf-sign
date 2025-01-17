<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\CertificateOutputNotFoundException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\FileNotFoundException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidCertificateContentException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidPFXException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\Invalidx509PrivateKeyException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\ProcessRunTimeException;
use NatanParmigiano\LaravelA1PdfSign\Sign\ManagedCertificate;
use NatanParmigiano\LaravelA1PdfSign\Tests\TestCase;

class CommandsTest extends TestCase
{
    /**
     * @throws FileNotFoundException
     * @throws InvalidCertificateContentException
     * @throws InvalidPFXException
     * @throws CertificateOutputNotFoundException
     * @throws ProcessRunTimeException
     * @throws Invalidx509PrivateKeyException
     */
    public function testWhenTheSignatureCommandIsSuccessfullyCompleted()
    {
        $cert = new ManagedCertificate;
        list($pfxPath, $pass) = $cert->makeDebugCertificate(true);
        $pdfPath    = __DIR__ . '/Resources/test.pdf';
        $fileName   = a1TempDir(true, '.pdf');
        $parameters = [
            'pdfPath'  => $pdfPath,
            'pfxPath'  => $pfxPath,
            'password' => $pass,
            'fileName' => $fileName
        ];

        $this->artisan('pdf:sign', $parameters)
             ->assertSuccessful()
             ->expectsOutput('Your PDF file is being signed!')
             ->expectsOutput("Your file has been signed and is available at: \"{$fileName}\"");

        File::delete([$pfxPath, $fileName]);
    }

    public function testWhenTheSignatureCommandDoesNotFinishSuccessfully()
    {
        $parameters = [
            'pdfPath'  => a1TempDir(true, '.pdf'),
            'pfxPath'  => a1TempDir(true, '.pfx'),
            'password' => Str::random(32),
            'fileName' => a1TempDir(true, '.pdf')
        ];

        $this->artisan('pdf:sign', $parameters)
//             ->assertFailed()
             ->expectsOutput('Your PDF file is being signed!')
             ->expectsOutputToContain('Could not sign your file, error occurred:');

    }

    /**
     * @throws FileNotFoundException
     * @throws ProcessRunTimeException
     * @throws Invalidx509PrivateKeyException
     * @throws Throwable
     * @throws InvalidCertificateContentException
     * @throws CertificateOutputNotFoundException
     * @throws InvalidPFXException
     */
    public function testWhenASignedPdfIsSuccessfullyValidated()
    {
        $cert = new ManagedCertificate;
        list($pfxPath, $pass) = $cert->makeDebugCertificate(true);

        $signed  = signPdfFromFile($pfxPath, $pass, __DIR__ . '/Resources/test.pdf');
        $pdfPath = a1TempDir(true, '.pdf');

        File::put($pdfPath, $signed);
        $fileExists = File::exists($pdfPath);

        $this->assertTrue($fileExists);

        $parameters = [
            'pdfPath' => $pdfPath
        ];

        $this->artisan('pdf:validate-signature', $parameters)
             ->assertSuccessful()
             ->expectsOutput('Your PDF document is being validated.')
             ->expectsOutput('Your PDF document is VALID');

        File::delete([$pfxPath, $pdfPath]);
    }

    public function testWhenAnUnsignedDocumentThrowsAnErrorWhenRunningAValidationCommand()
    {
        $pdfPath    = __DIR__ . '/Resources/test.pdf';
        $parameters = [
            'pdfPath' => $pdfPath
        ];

        $this->artisan('pdf:validate-signature', $parameters)
             ->assertFailed()
             ->expectsOutput('Your PDF document is being validated.')
             ->expectsOutputToContain('Unable to validate your file signature, an error occurred:');
    }
}
