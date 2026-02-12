/**
 * Shader Background - WebGL animated train scene
 * 
 * Provides an animated shader background for the landing page header.
 * Features a moving train with catenary wires and city buildings.
 */

/**
 * Initialize WebGL shader background
 * @param {string|HTMLCanvasElement} canvasElement - Canvas element or ID
 * @param {Object} options - Configuration options
 * @returns {Object} Control object with destroy method
 */
function initShaderBackground(canvasElement, options = {}) {
    const canvas = typeof canvasElement === 'string'
        ? document.getElementById(canvasElement)
        : canvasElement;

    if (!canvas) {
        console.warn('Canvas element not found');
        return null;
    }

    const gl = canvas.getContext('webgl2') || canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
        console.warn('WebGL not supported, falling back to gradient');
        canvas.style.background = 'linear-gradient(135deg, rgba(59, 130, 246, 0.4) 0%, rgba(236, 72, 153, 0.3) 50%, rgba(168, 85, 247, 0.3) 100%)';
        return null;
    }

    // Vertex shader
    const vertexShaderSource = `
        attribute vec2 position;
        void main() {
            gl_Position = vec4(position, 0.0, 1.0);
        }
    `;

    // Common shader code (WebGL 1.0 compatible)
    const commonShaderCode = `
        #define PI 3.14159265
        #define saturate(x) clamp(x,0.,1.)
        #define SUNDIR normalize(vec3(0.2,.3,2.))
        #define FOGCOLOR vec3(1.,.2,.1)

        float time;

        float smin( float a, float b, float k ) {
            float h = max(k-abs(a-b),0.0);
            return min(a, b) - h*h*0.25/k;
        }

        float smax( float a, float b, float k ) {
            k *= 1.4;
            float h = max(k-abs(a-b),0.0);
            return max(a, b) + h*h*h/(6.0*k*k);
        }

        float box( vec3 p, vec3 b, float r ) {
            vec3 q = abs(p) - b;
            return length(max(q,0.0)) + min(max(q.x,max(q.y,q.z)),0.0) - r;
        }

        float capsule( vec3 p, float h, float r ) {
            p.x -= clamp( p.x, 0.0, h );
            return length( p ) - r;
        }

        vec3 hash3( float n ) {
            return fract(sin(vec3(n, n+1.0, n+2.0)) * vec3(43758.5453123, 22578.1459123, 19642.3490423));
        }

        float hash( float p ) {
            return fract(sin(p)*43758.5453123);
        }

        mat2 rot(float v) {
            float a = cos(v);
            float b = sin(v);
            return mat2(a,-b,b,a);
        }

        float train(vec3 p) {
            vec3 op = p;
            float d = abs(box(p-vec3(0., 0., 0.), vec3(100.,1.5,5.), 0.))-.1;
            vec3 wp = p;
            wp.x = mod(wp.x+1.0, 4.0)-2.0;
            d = smax(d, -box(wp-vec3(0.,0.25,5.), vec3(1.2,.5,0.0), .3), 0.03);
            wp.x = mod(op.x-.8, 2.0)-1.0;
            d = smin(d, box(wp-vec3(0.,0.57,5.), vec3(.05,.05,0.1), .0), 0.001);
            p.x = mod(p.x-.8,2.)-1.;
            p.z = abs(p.z-4.3)-.3;
            d = smin(d, box(p-vec3(0.,-1., 0.), vec3(.3,.1-cos(p.z*PI*4.)*.01,.2),.05), 0.05);
            d = smin(d, box(p-vec3(0.4+pow(p.y+1.,2.)*.1,-0.38, 0.), vec3(.1-cos(p.z*PI*4.)*.01,.7,.2),.05), 0.1);
            d = smin(d, box(p-vec3(0.1,-1.3, 0.), vec3(.1,.2,.1),.05), 0.01);
            return d;
        }

        float catenary(vec3 p) {
            p.z -= 12.;
            vec3 pp = p;
            p.x = mod(p.x,100.)-50.;
            float d = box(p-vec3(0.,0.,0.), vec3(.0,3.,.0), .1);
            d = smin(d, box(p-vec3(0.,2.,0.), vec3(.0,0.,1.), .1), 0.05);
            p.z = abs(p.z-0.)-2.;
            d = smin(d, box(p-vec3(0.,2.2,-1.), vec3(.0,0.2,0.), .1), 0.01);
            pp.z = abs(pp.z-0.)-2.;
            d = min(d, capsule(p-vec3(-50.,2.4-abs(cos(pp.x*.01*PI)),-1.),10000.,.02));
            d = min(d, capsule(p-vec3(-50.,2.9-abs(cos(pp.x*.01*PI)),-2.),10000.,.02));
            return d;
        }

        float city(vec3 p) {
            vec3 pp = p;
            vec2 pId = floor((p.xz)/30.);
            vec3 rnd = hash3(pId.x + pId.y*1000.0);
            p.xz = mod(p.xz, vec2(30.))-15.;
            float h = 5.0+(pId.y-3.0)*5.0+rnd.x*20.0;
            float offset = (rnd.z*2.0-1.0)*10.0;
            float d = box(p-vec3(offset,-5.,0.), vec3(5.,h,5.), 0.1);
            d = min(d, box(p-vec3(offset,-5.,0.), vec3(1.,h+pow(rnd.y,4.)*10.,1.), 0.1));
            d = max(d,-pp.z+100.);
            d = max(d,pp.z-300.);
            return d*.6;
        }

        float map(vec3 p) {
            float d = train(p);
            p.x -= mix(time*4.5, time*15., saturate(time*.2));
            d = min(d, catenary(p));
            d = min(d, city(p));
            d = min(d, city(p+vec3(15.,0.,0.)));
            return d;
        }
    `;

    // Fragment shader
    const fragmentShaderSource = `
        precision highp float;
        uniform float u_time;
        uniform vec2 u_resolution;
        
        ${commonShaderCode}

        float trace(vec3 ro, vec3 rd, vec2 nearFar) {
            float t = nearFar.x;
            for(int i=0; i<48; i++) {
                float d = map(ro+rd*t);
                t += d;
                if( abs(d) < 0.01 || t > nearFar.y )
                    break;
            }
            return t;
        }

        vec3 normal(vec3 p) {
            vec2 eps = vec2(0.01, 0.);
            float d = map(p);
            vec3 n;
            n.x = d - map(p-eps.xyy);
            n.y = d - map(p-eps.yxy);
            n.z = d - map(p-eps.yyx);
            return normalize(n);
        }

        vec3 skyColor(vec3 rd) {
            vec3 col = FOGCOLOR;
            col += vec3(1.,.3,.1)*1. * pow(max(dot(rd,SUNDIR),0.),30.);
            col += vec3(1.,.3,.1)*10. * pow(max(dot(rd,SUNDIR),0.),10000.);
            return col;
        }

        void main() {
            time = u_time;
            
            // Clamp the visible shader to a centered 1920px region
            float maxWidth = 1920.0;
            float effectiveWidth = min(u_resolution.x, maxWidth);
            float sideMargin = (u_resolution.x - effectiveWidth) * 0.5;
            
            // Pixels outside the centered region are black
            if (gl_FragCoord.x < sideMargin || gl_FragCoord.x > u_resolution.x - sideMargin) {
                gl_FragColor = vec4(0.0, 0.0, 0.0, 1.0);
                return;
            }
            
            // Remap UVs to the clamped viewport so the scene never stretches beyond 1920px
            vec2 uv = vec2(
                (gl_FragCoord.x - sideMargin) / effectiveWidth,
                gl_FragCoord.y / u_resolution.y
            );
            vec2 v = -1.0 + 2.0 * uv;
            v.x *= effectiveWidth / u_resolution.y;
            
            vec3 ro = vec3(-1.5,-.4,1.2);
            vec3 rd = normalize(vec3(v, 2.5));
            rd.xz = rot(.15)*rd.xz;
            rd.yz = rot(.1)*rd.yz;
            
            float t = trace(ro,rd, vec2(0.,300.));
            vec3 p = ro + rd * t;
            vec3 n = normal(p);
            vec3 col = skyColor(rd);
            
            if (t < 300.) {
                vec3 diff = vec3(1.,.5,.3) * max(dot(n,SUNDIR),0.);
                vec3 amb = vec3(0.1,.15,.2);
                col = (diff*0.3 + amb*.3)*.02;
                
                if (p.z<6.) {
                    vec3 rrd = reflect(rd,n);
                    float fre = pow( saturate( 1.0 + dot(n,rd)), 8.0 );
                    vec3 rcol = skyColor(rrd);
                    col = mix(col, rcol, fre*.1);
                }
                
                col = mix(col, FOGCOLOR, smoothstep(100.,500.,t));
            }
            
            float godray = pow(max(dot(rd,SUNDIR),0.),50.) * 0.3;
            col += FOGCOLOR * godray * 0.01;
            
            col = pow(col, vec3(1./2.2));
            col = pow(col, vec3(.6,1.,.8*(uv.y*.2+.8)));
            
            float vignetting = pow(uv.x*uv.y*(1.-uv.x)*(1.-uv.y), .3)*2.5;
            col *= vignetting;
            
            // Edge fade: gradually appears from 1600px, fully black edges at 1920px+
            float fadeIntensity = smoothstep(1600.0, 1920.0, u_resolution.x);
            float edgeFade = 400.0 / effectiveWidth;
            float fadeL = smoothstep(0.0, edgeFade, uv.x);
            float fadeR = smoothstep(0.0, edgeFade, 1.0 - uv.x);
            float fade = fadeL * fadeR;
            fade = pow(fade, 0.6);
            col *= mix(1.0, fade, fadeIntensity);
            
            gl_FragColor = vec4(col, 1.0);
        }
    `;

    function createShader(gl, type, source) {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);

        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.error('Shader compile error:', gl.getShaderInfoLog(shader));
            gl.deleteShader(shader);
            return null;
        }

        return shader;
    }

    function createProgram(gl, vertexShader, fragmentShader) {
        const program = gl.createProgram();
        gl.attachShader(program, vertexShader);
        gl.attachShader(program, fragmentShader);
        gl.linkProgram(program);

        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            console.error('Program link error:', gl.getProgramInfoLog(program));
            gl.deleteProgram(program);
            return null;
        }

        return program;
    }

    const vertexShader = createShader(gl, gl.VERTEX_SHADER, vertexShaderSource);
    const fragmentShader = createShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    const program = createProgram(gl, vertexShader, fragmentShader);

    if (!program) {
        console.warn('Failed to create shader program');
        canvas.style.background = 'linear-gradient(135deg, rgba(59, 130, 246, 0.4) 0%, rgba(236, 72, 153, 0.3) 50%, rgba(168, 85, 247, 0.3) 100%)';
        return null;
    }

    // Set up geometry
    const positionBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
    const positions = new Float32Array([
        -1, -1,
         1, -1,
        -1,  1,
         1,  1,
    ]);
    gl.bufferData(gl.ARRAY_BUFFER, positions, gl.STATIC_DRAW);

    const positionLocation = gl.getAttribLocation(program, 'position');
    const timeLocation = gl.getUniformLocation(program, 'u_time');
    const resolutionLocation = gl.getUniformLocation(program, 'u_resolution');

    let animationFrameId = null;
    let isRunning = true;

    function resize() {
        const displayWidth = canvas.clientWidth;
        const displayHeight = canvas.clientHeight;

        if (canvas.width !== displayWidth || canvas.height !== displayHeight) {
            canvas.width = displayWidth;
            canvas.height = displayHeight;
            gl.viewport(0, 0, canvas.width, canvas.height);
        }
    }

    function render(time) {
        if (!isRunning) return;

        resize();

        gl.clearColor(0, 0, 0, 1);
        gl.clear(gl.COLOR_BUFFER_BIT);

        gl.useProgram(program);

        gl.enableVertexAttribArray(positionLocation);
        gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
        gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);

        gl.uniform1f(timeLocation, time * 0.001);
        gl.uniform2f(resolutionLocation, canvas.width, canvas.height);

        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);

        animationFrameId = requestAnimationFrame(render);
    }

    resize();
    window.addEventListener('resize', resize);
    animationFrameId = requestAnimationFrame(render);

    // Return control object
    return {
        destroy: function() {
            isRunning = false;
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
            window.removeEventListener('resize', resize);
            if (gl) {
                gl.deleteProgram(program);
                gl.deleteShader(vertexShader);
                gl.deleteShader(fragmentShader);
                gl.deleteBuffer(positionBuffer);
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
    module.exports = { initShaderBackground };
}
