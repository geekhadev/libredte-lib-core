<?php

declare(strict_types=1);

/**
 * LibreDTE: Biblioteca PHP (Núcleo).
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Affero de GNU publicada por
 * la Fundación para el Software Libre, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero SIN
 * GARANTÍA ALGUNA; ni siquiera la garantía implícita MERCANTIL o de APTITUD
 * PARA UN PROPÓSITO DETERMINADO. Consulte los detalles de la Licencia Pública
 * General Affero de GNU para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de
 * GNU junto a este programa.
 *
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace libredte\lib\Tests\Functional\Package\Billing\Component\Integration;

use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\BillingPackage;
use libredte\lib\Core\Package\Billing\Component\Integration\Exception\SiiTokenManagerException;
use libredte\lib\Core\Package\Billing\Component\Integration\IntegrationComponent;
use libredte\lib\Core\Package\Billing\Component\Integration\Support\SiiConnectionOptions;
use libredte\lib\Core\Package\Billing\Component\Integration\Worker\SiiLazyWorker;
use libredte\lib\Core\Package\Billing\Component\Integration\Worker\SiiTokenManagerWorker;
use libredte\lib\Core\Package\Billing\Component\Integration\Worker\SiiWsdlConsumerWorker;
use libredte\lib\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Application::class)]
#[CoversClass(BillingPackage::class)]
#[CoversClass(IntegrationComponent::class)]
#[CoversClass(SiiConnectionOptions::class)]
#[CoversClass(SiiLazyWorker::class)]
#[CoversClass(SiiTokenManagerWorker::class)]
#[CoversClass(SiiWsdlConsumerWorker::class)]
class SiiGetTokenFakeCertificateTest extends TestCase
{
    public function testSiiTokenManagerException(): void
    {
        $app = Application::getInstance();

        $certificate = $app
            ->getPrimePackage()
            ->getCertificateComponent()
            ->getFakerWorker()
            ->create()
        ;

        // Debe fallar la solicitud de token porque el certificado no es válido.
        $this->expectException(SiiTokenManagerException::class);

        $token = $app
            ->getBillingPackage()
            ->getIntegrationComponent()
            ->getSiiTokenManagerWorker()
            ->getToken($certificate)
        ;
    }
}
