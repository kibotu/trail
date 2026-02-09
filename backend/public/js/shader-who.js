/**
 * Shader Who - Protean Clouds WebGL Shader
 * 
 * Original shader by nimitz (@stormoid)
 * https://www.shadertoy.com/view/3l23Rh
 * License: Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License
 * 
 * Provides an animated volumetric cloud shader for user profile backgrounds.
 */

/**
 * Initialize Protean Clouds shader background
 * @param {string|HTMLCanvasElement} canvasElement - Canvas element or ID
 * @param {Object} options - Configuration options
 * @returns {Object} Control object with destroy, pause, and resume methods
 */
function initProteanCloudsShader(canvasElement, options = {}) {
    const canvas = typeof canvasElement === 'string'
        ? document.getElementById(canvasElement)
        : canvasElement;

    if (!canvas) {
        console.warn('Canvas element not found');
        return null;
    }

    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
        console.warn('WebGL not supported, shader will not render');
        return null;
    }

    // Vertex shader
    const vertexShaderSource = `
        attribute vec2 position;
        void main() {
            gl_Position = vec4(position, 0.0, 1.0);
        }
    `;

    // Fragment shader - Protean Clouds by nimitz
    const fragmentShaderSource = `
        precision highp float;

        uniform vec2 iResolution;
        uniform float iTime;
        uniform vec2 iMouse;

        mat2 rot(in float a) {
            float c = cos(a), s = sin(a);
            return mat2(c, s, -s, c);
        }

        const mat3 m3 = mat3(
            0.33338, 0.56034, -0.71817,
            -0.87887, 0.32651, -0.15323,
            0.15162, 0.69596, 0.61339
        ) * 1.93;

        float mag2(vec2 p) {
            return dot(p, p);
        }

        float linstep(in float mn, in float mx, in float x) {
            return clamp((x - mn) / (mx - mn), 0., 1.);
        }

        float prm1 = 0.;
        vec2 bsMo = vec2(0);

        vec2 disp(float t) {
            return vec2(sin(t * 0.22) * 1., cos(t * 0.175) * 1.) * 2.;
        }

        vec2 map(vec3 p) {
            vec3 p2 = p;
            p2.xy -= disp(p.z).xy;
            p.xy *= rot(sin(p.z + iTime) * (0.1 + prm1 * 0.05) + iTime * 0.09);
            float cl = mag2(p2.xy);
            float d = 0.;
            p *= .61;
            float z = 1.;
            float trk = 1.;
            float dspAmp = 0.1 + prm1 * 0.2;
            for(int i = 0; i < 5; i++) {
                p += sin(p.zxy * 0.75 * trk + iTime * trk * .8) * dspAmp;
                d -= abs(dot(cos(p), sin(p.yzx)) * z);
                z *= 0.57;
                trk *= 1.4;
                p = p * m3;
            }
            d = abs(d + prm1 * 3.) + prm1 * .3 - 2.5 + bsMo.y;
            return vec2(d + cl * .2 + 0.25, cl);
        }

        vec4 render(in vec3 ro, in vec3 rd, float time) {
            vec4 rez = vec4(0);
            const float ldst = 8.;
            vec3 lpos = vec3(disp(time + ldst) * 0.5, time + ldst);
            float t = 1.5;
            float fogT = 0.;
            for(int i = 0; i < 130; i++) {
                if(rez.a > 0.99) break;

                vec3 pos = ro + t * rd;
                vec2 mpv = map(pos);
                float den = clamp(mpv.x - 0.3, 0., 1.) * 1.12;
                float dn = clamp((mpv.x + 2.), 0., 3.);
                
                vec4 col = vec4(0);
                if (mpv.x > 0.6) {
                    col = vec4(sin(vec3(5., 0.4, 0.2) + mpv.y * 0.1 + sin(pos.z * 0.4) * 0.5 + 1.8) * 0.5 + 0.5, 0.08);
                    col *= den * den * den;
                    col.rgb *= linstep(4., -2.5, mpv.x) * 2.3;
                    float dif = clamp((den - map(pos + .8).x) / 9., 0.001, 1.);
                    dif += clamp((den - map(pos + .35).x) / 2.5, 0.001, 1.);
                    col.xyz *= den * (vec3(0.005, .045, .075) + 1.5 * vec3(0.033, 0.07, 0.03) * dif);
                }
                
                float fogC = exp(t * 0.2 - 2.2);
                col.rgba += vec4(0.06, 0.11, 0.11, 0.1) * clamp(fogC - fogT, 0., 1.);
                fogT = fogC;
                rez = rez + col * (1. - rez.a);
                t += clamp(0.5 - dn * dn * .05, 0.09, 0.3);
            }
            return clamp(rez, 0.0, 1.0);
        }

        float getsat(vec3 c) {
            float mi = min(min(c.x, c.y), c.z);
            float ma = max(max(c.x, c.y), c.z);
            return (ma - mi) / (ma + 1e-7);
        }

        vec3 iLerp(in vec3 a, in vec3 b, in float x) {
            vec3 ic = mix(a, b, x) + vec3(1e-6, 0., 0.);
            float sd = abs(getsat(ic) - mix(getsat(a), getsat(b), x));
            vec3 dir = normalize(vec3(2. * ic.x - ic.y - ic.z, 2. * ic.y - ic.x - ic.z, 2. * ic.z - ic.y - ic.x));
            float lgt = dot(vec3(1.0), ic);
            float ff = dot(dir, normalize(ic));
            ic += 1.5 * dir * sd * ff * lgt;
            return clamp(ic, 0., 1.);
        }

        void main() {
            vec2 fragCoord = gl_FragCoord.xy;
            vec2 q = fragCoord.xy / iResolution.xy;
            vec2 p = (gl_FragCoord.xy - 0.5 * iResolution.xy) / iResolution.y;
            bsMo = (iMouse.xy - 0.5 * iResolution.xy) / iResolution.y;
            
            float time = iTime * 3.;
            vec3 ro = vec3(0, 0, time);
            
            ro += vec3(sin(iTime) * 0.15, sin(iTime * 1.) * 0., 0);
                
            float dspAmp = .85;
            ro.xy += disp(ro.z) * dspAmp;
            float tgtDst = 3.5;
            
            vec3 target = normalize(ro - vec3(disp(time + tgtDst) * dspAmp, time + tgtDst));
            ro.x -= bsMo.x * 0.5;
            vec3 rightdir = normalize(cross(target, vec3(0, 1, 0)));
            vec3 updir = normalize(cross(rightdir, target));
            rightdir = normalize(cross(updir, target));
            vec3 rd = normalize((p.x * rightdir + p.y * updir) * 1. - target);
            rd.xy *= rot(-disp(time + 3.5).x * 0.05 + bsMo.x * 0.3);
            prm1 = smoothstep(-0.4, 0.4, sin(iTime * 0.3));
            vec4 scn = render(ro, rd, time);
                
            vec3 col = scn.rgb;
            col = iLerp(col.bgr, col.rgb, clamp(1. - prm1, 0.05, 1.));
            
            col = pow(col, vec3(.55, 0.65, 0.6)) * vec3(1., .97, .9);

            col *= pow(16.0 * q.x * q.y * (1.0 - q.x) * (1.0 - q.y), 0.12) * 0.7 + 0.3;
            
            gl_FragColor = vec4(col, 1.0);
        }
    `;

    // Compile shader
    function compileShader(source, type) {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);

        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.error('Shader compilation error:', gl.getShaderInfoLog(shader));
            gl.deleteShader(shader);
            return null;
        }

        return shader;
    }

    // Create program
    function createProgram(vertexShader, fragmentShader) {
        const program = gl.createProgram();
        gl.attachShader(program, vertexShader);
        gl.attachShader(program, fragmentShader);
        gl.linkProgram(program);

        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            console.error('Program linking error:', gl.getProgramInfoLog(program));
            gl.deleteProgram(program);
            return null;
        }

        return program;
    }

    const vertexShader = compileShader(vertexShaderSource, gl.VERTEX_SHADER);
    const fragmentShader = compileShader(fragmentShaderSource, gl.FRAGMENT_SHADER);

    if (!vertexShader || !fragmentShader) {
        console.warn('Failed to compile shaders');
        return null;
    }

    const program = createProgram(vertexShader, fragmentShader);

    if (!program) {
        console.warn('Failed to create shader program');
        return null;
    }

    gl.useProgram(program);

    // Create fullscreen quad
    const positions = new Float32Array([
        -1, -1,
         1, -1,
        -1,  1,
         1,  1
    ]);

    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, positions, gl.STATIC_DRAW);

    const positionLocation = gl.getAttribLocation(program, 'position');
    gl.enableVertexAttribArray(positionLocation);
    gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);

    // Get uniform locations
    const iResolutionLocation = gl.getUniformLocation(program, 'iResolution');
    const iTimeLocation = gl.getUniformLocation(program, 'iTime');
    const iMouseLocation = gl.getUniformLocation(program, 'iMouse');

    // Mouse tracking
    let mouseX = 0;
    let mouseY = 0;

    function handleMouseMove(e) {
        const rect = canvas.getBoundingClientRect();
        mouseX = e.clientX - rect.left;
        mouseY = canvas.height - (e.clientY - rect.top);
    }

    function handleTouchMove(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches[0];
        mouseX = touch.clientX - rect.left;
        mouseY = canvas.height - (touch.clientY - rect.top);
    }

    canvas.addEventListener('mousemove', handleMouseMove);
    canvas.addEventListener('touchmove', handleTouchMove, { passive: false });

    // Animation state
    let animationFrameId = null;
    let isRunning = true;
    const startTime = Date.now();

    // Resize canvas
    function resizeCanvas() {
        const displayWidth = canvas.clientWidth;
        const displayHeight = canvas.clientHeight;

        if (canvas.width !== displayWidth || canvas.height !== displayHeight) {
            canvas.width = displayWidth;
            canvas.height = displayHeight;
            gl.viewport(0, 0, canvas.width, canvas.height);
        }
    }

    // Animation loop
    function render() {
        if (!isRunning) return;

        resizeCanvas();

        const currentTime = (Date.now() - startTime) / 1000;

        gl.uniform2f(iResolutionLocation, canvas.width, canvas.height);
        gl.uniform1f(iTimeLocation, currentTime);
        gl.uniform2f(iMouseLocation, mouseX, mouseY);

        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);

        animationFrameId = requestAnimationFrame(render);
    }

    // Start rendering
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    animationFrameId = requestAnimationFrame(render);

    // Return control object
    return {
        destroy: function() {
            isRunning = false;
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
            window.removeEventListener('resize', resizeCanvas);
            canvas.removeEventListener('mousemove', handleMouseMove);
            canvas.removeEventListener('touchmove', handleTouchMove);
            
            if (gl) {
                gl.deleteProgram(program);
                gl.deleteShader(vertexShader);
                gl.deleteShader(fragmentShader);
                gl.deleteBuffer(buffer);
            }
        },
        pause: function() {
            isRunning = false;
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
        },
        resume: function() {
            if (!isRunning) {
                isRunning = true;
                animationFrameId = requestAnimationFrame(render);
            }
        }
    };
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initProteanCloudsShader };
}
