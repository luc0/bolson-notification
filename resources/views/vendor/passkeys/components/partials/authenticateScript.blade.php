<script>
    // Función global para autenticación con passkey
    window.authenticateWithPasskey = async function() {
        try {
            console.log('=== INICIANDO AUTENTICACIÓN CON PASSKEY ===');
            console.log('User Agent:', navigator.userAgent);
            console.log('HTTPS:', location.protocol === 'https:');
            console.log('Localhost:', location.hostname === 'localhost' || location.hostname === '127.0.0.1');
            
            // Verificar soporte básico de WebAuthn
            if (!window.PublicKeyCredential) {
                console.error('PublicKeyCredential no está disponible');
                alert('Tu navegador no soporta WebAuthn/Passkeys. Por favor usa un navegador más reciente.');
                return;
            }

            // Verificar funciones de SimpleWebAuthn inmediatamente
            if (typeof window.startAuthentication === 'undefined') {
                console.log('⚠️ startAuthentication no disponible, intentando cargar...');
                
                // Intentar cargar manualmente primero
                const loaded = await loadWebAuthnManually();
                if (loaded) {
                    console.log('✅ Funciones de WebAuthn cargadas manualmente');
                } else {
                    console.log('⚠️ Fallo la carga manual, intentando desde CDN...');
                    const cdnLoaded = await loadWebAuthnFromCDN();
                    if (cdnLoaded) {
                        console.log('✅ Funciones de WebAuthn cargadas desde CDN');
                    } else {
                        console.error('❌ No se pudieron cargar las funciones de WebAuthn');
                        alert('Error: Las funciones de WebAuthn no están disponibles. Por favor recarga la página e intenta nuevamente.');
                        return;
                    }
                }
            } else {
                console.log('✅ Funciones de WebAuthn disponibles inmediatamente');
            }

            // Verificar soporte de WebAuthn
            if (typeof window.browserSupportsWebAuthn === 'function') {
                const supportsWebAuthn = await window.browserSupportsWebAuthn();
                console.log('Soporte WebAuthn:', supportsWebAuthn);
                if (!supportsWebAuthn) {
                    alert('Tu navegador no soporta WebAuthn. Por favor usa un navegador más reciente.');
                    return;
                }
            }

            console.log('Obteniendo opciones de autenticación...');
            const response = await fetch('{{ route('passkeys.authentication_options') }}', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
            }

            const options = await response.json();
            console.log('Opciones obtenidas:', options);

            // Configurar timeout más largo para evitar errores de timeout
            const timeoutMs = 60000; // 60 segundos
            console.log('Iniciando autenticación WebAuthn con timeout de', timeoutMs, 'ms...');
            
            const startAuthenticationResponse = await window.startAuthentication({ 
                optionsJSON: options,
                timeout: timeoutMs
            });

            console.log('Respuesta de autenticación:', startAuthenticationResponse);

            const form = document.getElementById('passkey-login-form');
            if (!form) {
                throw new Error('No se encontró el formulario de login');
            }

            form.addEventListener('formdata', ({formData}) => {
                formData.set('start_authentication_response', JSON.stringify(startAuthenticationResponse));
            });

            console.log('Enviando formulario...');
            form.submit();
        } catch (error) {
            console.error('Error en authenticateWithPasskey:', error);
            
            // Mensajes de error más específicos
            let errorMessage = error.message;
            if (error.name === 'NotAllowedError') {
                errorMessage = 'La autenticación fue cancelada o no permitida. Asegúrate de que tu dispositivo soporte passkeys.';
            } else if (error.name === 'TimeoutError') {
                errorMessage = 'La autenticación tardó demasiado tiempo. Intenta nuevamente.';
            } else if (error.message.includes('timeout')) {
                errorMessage = 'La operación tardó demasiado tiempo. Intenta nuevamente.';
            } else if (error.message.includes('not allowed')) {
                errorMessage = 'La operación no está permitida. Verifica que tu dispositivo soporte passkeys.';
            }
            
            alert('Error al autenticar con passkey: ' + errorMessage);
        }
    };

    // Función para esperar a que las funciones de WebAuthn estén disponibles
    function waitForWebAuthn() {
        return new Promise((resolve, reject) => {
            let attempts = 0;
            const maxAttempts = 100; // 10 segundos máximo
            
            console.log('🔍 Iniciando búsqueda de funciones WebAuthn...');
            
            const checkInterval = setInterval(() => {
                attempts++;
                console.log(`🔍 Intento ${attempts}/${maxAttempts}:`, {
                    startAuthentication: typeof window.startAuthentication,
                    browserSupportsWebAuthn: typeof window.browserSupportsWebAuthn,
                    startRegistration: typeof window.startRegistration
                });
                
                if (typeof window.startAuthentication !== 'undefined' && 
                    typeof window.browserSupportsWebAuthn !== 'undefined') {
                    clearInterval(checkInterval);
                    console.log('✅ Funciones WebAuthn encontradas después de', attempts * 100, 'ms');
                    resolve(true);
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.error('❌ Timeout: Funciones WebAuthn no encontradas después de', maxAttempts * 100, 'ms');
                    console.log('Scripts disponibles en window:', Object.keys(window).filter(k => 
                        k.includes('start') || k.includes('Auth') || k.includes('browser') || k.includes('WebAuthn')
                    ));
                    reject(new Error('Las funciones de WebAuthn no se cargaron a tiempo'));
                }
            }, 100);
        });
    }

    // Función para cargar WebAuthn manualmente si no está disponible
    async function loadWebAuthnManually() {
        try {
            console.log('🔄 Intentando cargar WebAuthn manualmente...');
            
            // Verificar si los scripts están en el DOM
            const scripts = Array.from(document.scripts);
            console.log('Scripts en el DOM:', scripts.map(s => s.src || s.innerHTML.substring(0, 100)));
            
            // Buscar el script de la aplicación
            const appScript = scripts.find(s => s.src && s.src.includes('app-'));
            if (appScript) {
                console.log('Script de app encontrado:', appScript.src);
                console.log('Estado del script:', appScript.readyState);
                
                // Verificar si el script está completamente cargado
                if (appScript.readyState === 'complete' || appScript.readyState === 'loaded') {
                    console.log('Script de app completamente cargado');
                } else {
                    console.log('Script de app aún cargando, esperando...');
                    await new Promise((resolve) => {
                        const timeout = setTimeout(resolve, 10000); // Timeout de 10 segundos
                        
                        appScript.addEventListener('load', () => {
                            clearTimeout(timeout);
                            console.log('✅ Script de app cargado exitosamente');
                            resolve();
                        });
                        
                        appScript.addEventListener('error', (error) => {
                            clearTimeout(timeout);
                            console.error('❌ Error cargando script de app:', error);
                            resolve();
                        });
                        
                        // También verificar periódicamente si las funciones están disponibles
                        const checkInterval = setInterval(() => {
                            if (typeof window.startAuthentication !== 'undefined') {
                                clearTimeout(timeout);
                                clearInterval(checkInterval);
                                console.log('✅ Funciones WebAuthn disponibles durante la espera');
                                resolve();
                            }
                        }, 500);
                        
                        // Limpiar el intervalo cuando se resuelve la promesa
                        setTimeout(() => clearInterval(checkInterval), 10000);
                    });
                }
            }
            
            // Verificar nuevamente si las funciones están disponibles
            if (typeof window.startAuthentication !== 'undefined') {
                console.log('✅ Funciones WebAuthn disponibles después de esperar al script');
                return true;
            }
            
            // Como último recurso, intentar cargar desde CDN
            console.log('🔄 Intentando cargar SimpleWebAuthn desde CDN...');
            const cdnLoaded = await loadWebAuthnFromCDN();
            if (cdnLoaded) {
                return true;
            }
            
            // Último recurso: crear funciones básicas usando la API nativa
            console.log('🔄 Creando funciones WebAuthn básicas usando API nativa...');
            return createNativeWebAuthnFunctions();
            
        } catch (error) {
            console.error('Error cargando WebAuthn manualmente:', error);
            return false;
        }
    }

    // Función para crear funciones WebAuthn básicas usando API nativa
    function createNativeWebAuthnFunctions() {
        try {
            console.log('🔧 Creando funciones WebAuthn nativas...');
            
            // Función básica de soporte
            window.browserSupportsWebAuthn = async function() {
                return !!window.PublicKeyCredential;
            };
            
            // Función básica de autenticación
            window.startAuthentication = async function({ optionsJSON }) {
                console.log('🔐 Iniciando autenticación nativa...');
                
                const options = JSON.parse(optionsJSON);
                
                // Crear las opciones de autenticación
                const authOptions = {
                    challenge: new Uint8Array(Object.values(options.challenge)),
                    timeout: options.timeout || 60000,
                    allowCredentials: options.allowCredentials?.map(cred => ({
                        id: new Uint8Array(Object.values(cred.id)),
                        type: cred.type,
                        transports: cred.transports
                    })) || [],
                    userVerification: options.userVerification || 'preferred'
                };
                
                console.log('Opciones de autenticación:', authOptions);
                
                // Llamar a la API nativa
                const credential = await navigator.credentials.get({
                    publicKey: authOptions
                });
                
                console.log('Credencial obtenida:', credential);
                
                // Convertir la respuesta al formato esperado por SimpleWebAuthn
                const response = {
                    id: credential.id,
                    rawId: Array.from(new Uint8Array(credential.rawId)),
                    response: {
                        authenticatorData: Array.from(new Uint8Array(credential.response.authenticatorData)),
                        clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON)),
                        signature: Array.from(new Uint8Array(credential.response.signature)),
                        userHandle: credential.response.userHandle ? Array.from(new Uint8Array(credential.response.userHandle)) : null
                    },
                    type: credential.type
                };
                
                console.log('Respuesta formateada:', response);
                return response;
            };
            
            // Función básica de registro
            window.startRegistration = async function({ optionsJSON }) {
                console.log('📝 Iniciando registro nativo...');
                
                const options = JSON.parse(optionsJSON);
                
                // Crear las opciones de registro
                const regOptions = {
                    challenge: new Uint8Array(Object.values(options.challenge)),
                    rp: options.rp,
                    user: {
                        id: new Uint8Array(Object.values(options.user.id)),
                        name: options.user.name,
                        displayName: options.user.displayName
                    },
                    pubKeyCredParams: options.pubKeyCredParams,
                    timeout: options.timeout || 60000,
                    attestation: options.attestation || 'none',
                    authenticatorSelection: options.authenticatorSelection || {
                        authenticatorAttachment: 'platform',
                        userVerification: 'preferred'
                    }
                };
                
                console.log('Opciones de registro:', regOptions);
                
                // Llamar a la API nativa
                const credential = await navigator.credentials.create({
                    publicKey: regOptions
                });
                
                console.log('Credencial creada:', credential);
                
                // Convertir la respuesta al formato esperado por SimpleWebAuthn
                const response = {
                    id: credential.id,
                    rawId: Array.from(new Uint8Array(credential.rawId)),
                    response: {
                        attestationObject: Array.from(new Uint8Array(credential.response.attestationObject)),
                        clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON))
                    },
                    type: credential.type
                };
                
                console.log('Respuesta formateada:', response);
                return response;
            };
            
            console.log('✅ Funciones WebAuthn nativas creadas exitosamente');
            return true;
            
        } catch (error) {
            console.error('❌ Error creando funciones WebAuthn nativas:', error);
            return false;
        }
    }

    // Función para cargar WebAuthn desde CDN como fallback
    async function loadWebAuthnFromCDN() {
        try {
            console.log('📦 Cargando SimpleWebAuthn desde CDN...');
            
            // Crear script dinámicamente con una versión más reciente
            const script = document.createElement('script');
            script.type = 'module';
            script.innerHTML = `
                try {
                    const { browserSupportsWebAuthn, startAuthentication, startRegistration } = await import('https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@9.2.2/dist/simplewebauthn-browser.min.js');
                    
                    window.browserSupportsWebAuthn = browserSupportsWebAuthn;
                    window.startAuthentication = startAuthentication;
                    window.startRegistration = startRegistration;
                    
                    console.log('✅ SimpleWebAuthn cargado desde CDN exitosamente');
                    
                    // Disparar evento personalizado para notificar que se cargó
                    window.dispatchEvent(new CustomEvent('webauthnLoaded'));
                } catch (error) {
                    console.error('❌ Error importando desde CDN:', error);
                    window.dispatchEvent(new CustomEvent('webauthnError', { detail: error }));
                }
            `;
            
            document.head.appendChild(script);
            
            // Esperar a que se cargue usando eventos personalizados
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Timeout cargando CDN'));
                }, 15000);
                
                window.addEventListener('webauthnLoaded', () => {
                    clearTimeout(timeout);
                    resolve();
                }, { once: true });
                
                window.addEventListener('webauthnError', (event) => {
                    clearTimeout(timeout);
                    reject(event.detail);
                }, { once: true });
            });
            
            // Verificar si funcionó
            if (typeof window.startAuthentication !== 'undefined') {
                console.log('✅ Funciones WebAuthn cargadas desde CDN y verificadas');
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Error cargando desde CDN:', error);
            return false;
        }
    }

    // Función para inicializar cuando todo esté listo
    function initializePasskeyScript() {
        console.log('=== INFORMACIÓN DEL NAVEGADOR ===');
        console.log('DOM cargado, función authenticateWithPasskey disponible');
        
        // Verificar inmediatamente el estado de las funciones
        console.log('Estado inicial de funciones WebAuthn:');
        console.log('- startAuthentication:', typeof window.startAuthentication);
        console.log('- browserSupportsWebAuthn:', typeof window.browserSupportsWebAuthn);
        console.log('- startRegistration:', typeof window.startRegistration);
        console.log('- PublicKeyCredential:', typeof window.PublicKeyCredential);
        
        // Verificar todos los scripts cargados
        const scripts = Array.from(document.scripts);
        console.log('Todos los scripts en el DOM:', scripts.map(s => ({
            src: s.src,
            loaded: s.readyState || 'unknown',
            hasContent: s.innerHTML.length > 0
        })));
        
        // Buscar específicamente el script de la aplicación
        const appScript = scripts.find(s => s.src && s.src.includes('app-'));
        if (appScript) {
            console.log('Script de app encontrado:', {
                src: appScript.src,
                readyState: appScript.readyState,
                loaded: appScript.readyState === 'complete'
            });
        } else {
            console.log('⚠️ No se encontró script de app');
        }
        
        // Verificar si hay errores en la consola
        console.log('Verificando si hay errores de JavaScript...');
        
        // Intentar cargar WebAuthn si no está disponible
        if (typeof window.startAuthentication === 'undefined') {
            console.log('🔄 Funciones WebAuthn no disponibles, intentando cargar...');
            loadWebAuthnManually().then(loaded => {
                if (loaded) {
                    console.log('✅ Funciones WebAuthn cargadas manualmente');
                } else {
                    console.log('⚠️ Fallo la carga manual');
                }
            }).catch(error => {
                console.error('❌ Error en carga manual:', error);
            });
        }
        
        console.log('Protocolo:', location.protocol);
        console.log('Host:', location.host);
    }

    // Múltiples estrategias para asegurar que se ejecute después de que todo esté cargado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePasskeyScript);
    } else {
        // DOM ya está cargado
        initializePasskeyScript();
    }

    // También intentar después de que la ventana esté completamente cargada
    window.addEventListener('load', function() {
        console.log('🔄 Window load event - verificando funciones WebAuthn nuevamente');
        if (typeof window.startAuthentication === 'undefined') {
            console.log('⚠️ Funciones WebAuthn aún no disponibles después de window.load');
        } else {
            console.log('✅ Funciones WebAuthn disponibles después de window.load');
        }
    });
</script>
