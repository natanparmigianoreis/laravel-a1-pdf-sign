<?php

namespace NatanParmigiano\LaravelA1PdfSign\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\CertificateOutputNotFoundException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\FileNotFoundException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidCertificateContentException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidPFXException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\Invalidx509PrivateKeyException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\ProcessRunTimeException;
use NatanParmigiano\LaravelA1PdfSign\Sign\ManagedCertificate;
use Throwable;

class HelpersTest extends TestCase
{
    /**
     * @throws FileNotFoundException
     * @throws ProcessRunTimeException
     * @throws Invalidx509PrivateKeyException
     * @throws Throwable
     * @throws InvalidCertificateContentException
     * @throws InvalidPFXException
     * @throws CertificateOutputNotFoundException
     */
    public function testWhenAFileIsSignedByTheSignPdfFromFileHelper()
    {
        $cert = new ManagedCertificate;
        list($pfxPath, $pass) = $cert->makeDebugCertificate(true);

        $signed = signPdfFromFile($pfxPath, $pass, __DIR__ . '/Resources/test.pdf');
        $pdfPath = a1TempDir(true, '.pdf');

        File::put($pdfPath, $signed);
        $fileExists = File::exists($pdfPath);

        $this->assertTrue($fileExists);
        File::delete([$pfxPath, $pdfPath]);
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
    public function testWhenAFileIsSignedByTheSignPdfFromUploadHelper()
    {
        $cert = new ManagedCertificate;
        list($pfxPath, $pass) = $cert->makeDebugCertificate(true);

        $uploadedFile = new UploadedFile($pfxPath, 'testCertificate.pfx', null, null, true);
        $signed = signPdfFromUpload($uploadedFile, $pass, __DIR__ . '/Resources/test.pdf');
        $pdfPath = a1TempDir(true, '.pdf');

        File::put($pdfPath, $signed);
        $fileExists = File::exists($pdfPath);

        $this->assertTrue($fileExists);
        File::delete([$pfxPath, $pdfPath]);
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
    public function testWhenCertificateDataIsEncrypted()
    {
        $cert = new ManagedCertificate;
        list($pfxPath, $pass) = $cert->makeDebugCertificate(true);

        $encryptedData = encryptCertData($pfxPath, $pass);

        foreach (['certificate', 'password', 'hash'] as $key) {
            $this->assertArrayHasKey($key, $encryptedData->toArray());
        }

        File::delete([$pfxPath]);
    }

    public function testWhenTheA1TempDirHelperCreatesTheFilesCorrectly()
    {
        $this->assertTrue(
            File::isDirectory(a1TempDir())
        );

        $this->assertTrue(
            Str::endsWith(a1TempDir(true), '.pfx')
        );

        $this->assertTrue(
            Str::endsWith(
                a1TempDir(true, '.pdf'),
                '.pdf'
            )
        );
    }

    /**
     * @throws FileNotFoundException
     * @throws ProcessRunTimeException
     * @throws Invalidx509PrivateKeyException
     * @throws InvalidCertificateContentException
     * @throws Throwable
     * @throws CertificateOutputNotFoundException
     * @throws InvalidPFXException
     */
    public function testWhenASignedPdfFileIsCorrectlyValidatedByTheValidatePdfSignatureHelper()
    {
        $cert = new ManagedCertificate;
        list($pfxPath, $pass) = $cert->makeDebugCertificate(true);

        $signed = signPdfFromFile($pfxPath, $pass, __DIR__ . '/Resources/test.pdf');
        $pdfPath = a1TempDir(true, '.pdf');

        File::put($pdfPath, $signed);
        $fileExists = File::exists($pdfPath);

        $this->assertTrue($fileExists);

        $validation = validatePdfSignature($pdfPath);
        $this->assertTrue($validation->isValidated);

        File::delete([$pfxPath, $pdfPath]);
    }
}
