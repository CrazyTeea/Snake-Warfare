export class GameRenderer {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.snakes = new Map();
        this.targetSnakes = new Map();
        this.foods = new Map();
        this.particles = [];
        this.mySnakeId = null;
        this.camera = { x: 0, y: 0 };
        this.mapSize = 5000;
    }

    setMySnakeId(id) {
        this.mySnakeId = id !== null && id !== undefined ? String(id) : null;
    }

    addExplosion(x, y, color, count = 8, speedMult = 1) {
        for (let i = 0; i < count; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = (Math.random() * 4 + 1.5) * speedMult;
            this.particles.push({
                x,
                y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                color: color || '#38bdf8',
                radius: Math.random() * 3.5 + 1.5,
                alpha: 1.0,
                decay: Math.random() * 0.04 + 0.02
            });
        }
    }

    updateServerState(payload) {
        if (!payload) return;

        const rawSnakes = payload.s || payload.snakes || [];
        const eatenFoodIds = payload.e || payload.eatenFoodIds || payload.eatenFood || [];
        const rawSpawnedFood = payload.f || payload.spawnedFood || [];

        const activeIds = new Set();

        for (const rawSnake of rawSnakes) {
            const snake = {
                id: String(rawSnake.i ?? rawSnake.id),
                username: rawSnake.u ?? rawSnake.username ?? 'Snake',
                color: rawSnake.c ?? rawSnake.color ?? '#38bdf8',
                angle: Number(rawSnake.a ?? rawSnake.angle ?? 0),
                shieldActive: Boolean(rawSnake.sh ?? rawSnake.shieldActive),
                invisible: Boolean(rawSnake.inv ?? rawSnake.invisible),
                equippedBuffs: rawSnake.b ?? rawSnake.equippedBuffs ?? {},
                boost: Boolean(rawSnake.bt ?? rawSnake.boost),
                segments: Array.isArray(rawSnake.p)
                    ? rawSnake.p.map(p => Array.isArray(p) ? { x: Number(p[0]), y: Number(p[1]) } : { x: Number(p.x), y: Number(p.y) })
                    : (rawSnake.segments || []).map(s => ({ x: Number(s.x), y: Number(s.y) }))
            };

            activeIds.add(snake.id);
            this.targetSnakes.set(snake.id, snake);

            if (!this.snakes.has(snake.id)) {
                this.snakes.set(snake.id, {
                    ...snake,
                    segments: snake.segments.map(s => ({ x: s.x, y: s.y }))
                });
            }
        }

        for (const [id, snake] of this.snakes) {
            if (!activeIds.has(id)) {
                if (snake.segments) {
                    for (let i = 0; i < snake.segments.length; i += 2) {
                        const seg = snake.segments[i];
                        if (seg) this.addExplosion(seg.x, seg.y, snake.color || '#ef4444', 4, 1.2);
                    }
                }
                this.snakes.delete(id);
                this.targetSnakes.delete(id);
            }
        }

        for (const rawFoodId of eatenFoodIds) {
            const foodKey = typeof rawFoodId === 'object' && rawFoodId !== null ? String(rawFoodId.id) : String(rawFoodId);
            const food = this.foods.get(foodKey);
            if (food) {
                this.addExplosion(food.x, food.y, food.color || '#38bdf8', 3, 0.8);
                this.foods.delete(foodKey);
            }
        }

        for (const rawFood of rawSpawnedFood) {
            const food = Array.isArray(rawFood) ? {
                id: String(rawFood[0]),
                x: Number(rawFood[1]),
                y: Number(rawFood[2]),
                color: String(rawFood[3]),
                value: Number(rawFood[4])
            } : {
                id: String(rawFood.id),
                x: Number(rawFood.x),
                y: Number(rawFood.y),
                color: String(rawFood.color),
                value: Number(rawFood.value)
            };

            this.foods.set(food.id, food);
        }
    }

    setInitialFoods(foodsArray) {
        this.foods.clear();
        for (const rawFood of foodsArray) {
            const food = Array.isArray(rawFood) ? {
                id: String(rawFood[0]),
                x: Number(rawFood[1]),
                y: Number(rawFood[2]),
                color: String(rawFood[3]),
                value: Number(rawFood[4])
            } : {
                id: String(rawFood.id),
                x: Number(rawFood.x),
                y: Number(rawFood.y),
                color: String(rawFood.color),
                value: Number(rawFood.value)
            };
            this.foods.set(food.id, food);
        }
    }

    lerpAngle(a, b, t) {
        let diff = b - a;
        while (diff < -Math.PI) diff += Math.PI * 2;
        while (diff > Math.PI) diff -= Math.PI * 2;
        return a + diff * t;
    }

    updateParticles() {
        if (this.particles.length > 250) {
            this.particles.splice(0, this.particles.length - 250);
        }

        for (let i = this.particles.length - 1; i >= 0; i--) {
            const p = this.particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vx *= 0.95;
            p.vy *= 0.95;
            p.alpha -= p.decay;

            if (p.alpha <= 0) {
                this.particles.splice(i, 1);
            }
        }
    }

    interpolate(dt = 0.016) {
        const frameDt = Math.min(dt, 0.1);

        for (const [id, current] of this.snakes) {
            const target = this.targetSnakes.get(id);
            if (!target || !target.segments || target.segments.length === 0) continue;

            current.angle = this.lerpAngle(current.angle || 0, target.angle || 0, 0.25);
            current.color = target.color;
            current.username = target.username;
            current.shieldActive = Boolean(target.shieldActive);
            current.invisible = Boolean(target.invisible);
            current.equippedBuffs = target.equippedBuffs;
            current.boost = Boolean(target.boost);

            if (!current.segments || current.segments.length === 0) {
                current.segments = target.segments.map(s => ({ x: s.x, y: s.y }));
            }

            while (current.segments.length < target.segments.length) {
                const tail = current.segments[current.segments.length - 1] || target.segments[0];
                current.segments.push({ x: tail.x, y: tail.y });
            }
            if (current.segments.length > target.segments.length) {
                current.segments.splice(target.segments.length);
            }

            // 🚀 Плавная предсказательная экстраполяция (Dead Reckoning) 60 FPS для головы
            const speed = (current.boost ? 12.0 : 6.0) * 20; // Пикселей в секунду
            const targetHead = target.segments[0];
            const currentHead = current.segments[0];

            const distToTarget = Math.hypot(targetHead.x - currentHead.x, targetHead.y - currentHead.y);

            if (distToTarget > 150) {
                currentHead.x = targetHead.x;
                currentHead.y = targetHead.y;
            } else {
                const forwardDist = speed * frameDt;
                const predX = currentHead.x + Math.cos(current.angle) * forwardDist;
                const predY = currentHead.y + Math.sin(current.angle) * forwardDist;

                const lerpRatio = Math.min(1.0, frameDt * 10.0);
                currentHead.x = predX + (targetHead.x - predX) * lerpRatio;
                currentHead.y = predY + (targetHead.y - predY) * lerpRatio;
            }

            currentHead.x = Math.max(0, Math.min(this.mapSize, currentHead.x));
            currentHead.y = Math.max(0, Math.min(this.mapSize, currentHead.y));

            // 🐍 Автономное связывание звеньев тела без разрывов
            const segmentDist = 15.0;
            for (let i = 1; i < current.segments.length; i++) {
                const prev = current.segments[i - 1];
                const curr = current.segments[i];
                const dx = curr.x - prev.x;
                const dy = curr.y - prev.y;
                const dist = Math.hypot(dx, dy);

                if (dist > 0.001) {
                    const ratio = segmentDist / dist;
                    curr.x = prev.x + dx * ratio;
                    curr.y = prev.y + dy * ratio;
                }
            }

            if (current.boost && Math.random() < 0.3) {
                const tail = current.segments[current.segments.length - 1];
                if (tail) {
                    this.addExplosion(tail.x, tail.y, current.color || '#38bdf8', 1, 0.4);
                }
            }
        }

        if (this.mySnakeId && this.snakes.has(this.mySnakeId)) {
            const mySnake = this.snakes.get(this.mySnakeId);
            if (mySnake && mySnake.segments && mySnake.segments[0]) {
                const head = mySnake.segments[0];
                const targetCamX = head.x - this.canvas.width / 2;
                const targetCamY = head.y - this.canvas.height / 2;
                this.camera.x += (targetCamX - this.camera.x) * 0.2;
                this.camera.y += (targetCamY - this.camera.y) * 0.2;
            }
        }
    }

    render(dt = 0.016) {
        this.interpolate(dt);
        this.updateParticles();

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.ctx.save();
        this.ctx.translate(-this.camera.x, -this.camera.y);

        this.drawGrid();
        this.drawFoods();
        this.drawParticles();
        this.drawSnakes();

        this.ctx.restore();

        this.drawMinimap();
    }

    drawParticles() {
        for (const p of this.particles) {
            this.ctx.save();
            this.ctx.globalAlpha = Math.max(0, p.alpha);
            this.ctx.fillStyle = p.color;
            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            this.ctx.fill();
            this.ctx.restore();
        }
    }

    drawMinimap() {
        const isMobile = this.canvas.width < 768;
        const size = isMobile ? 85 : 120;
        const padding = isMobile ? 10 : 16;
        const x = this.canvas.width - size - padding;
        const y = this.canvas.height - size - padding;

        this.ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
        this.ctx.strokeStyle = '#334155';
        this.ctx.lineWidth = 1.5;
        this.ctx.fillRect(x, y, size, size);
        this.ctx.strokeRect(x, y, size, size);

        const scale = size / this.mapSize;

        for (const [id, snake] of this.snakes) {
            if (!snake.segments || !snake.segments[0]) continue;

            const isMe = id === this.mySnakeId;

            if (snake.invisible && !isMe) {
                continue;
            }

            const head = snake.segments[0];

            this.ctx.beginPath();
            this.ctx.arc(
                x + head.x * scale,
                y + head.y * scale,
                isMe ? (isMobile ? 3 : 4) : (isMobile ? 2 : 2.5),
                0,
                Math.PI * 2
            );

            this.ctx.fillStyle = snake.color || (isMe ? '#38bdf8' : '#ef4444');
            this.ctx.fill();

            if (isMe) {
                this.ctx.strokeStyle = '#ffffff';
                this.ctx.lineWidth = 1.5;
                this.ctx.stroke();
            }
        }
    }

    drawGrid() {
        const gridSize = 100;
        this.ctx.strokeStyle = '#1e293b';
        this.ctx.lineWidth = 1;

        const startX = Math.floor(this.camera.x / gridSize) * gridSize;
        const endX = startX + this.canvas.width + gridSize;
        const startY = Math.floor(this.camera.y / gridSize) * gridSize;
        const endY = startY + this.canvas.height + gridSize;

        for (let x = startX; x < endX; x += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(x, startY);
            this.ctx.lineTo(x, endY);
            this.ctx.stroke();
        }

        for (let y = startY; y < endY; y += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(startX, y);
            this.ctx.lineTo(endX, y);
            this.ctx.stroke();
        }

        this.ctx.strokeStyle = '#ef4444';
        this.ctx.lineWidth = 5;
        this.ctx.strokeRect(0, 0, this.mapSize, this.mapSize);
    }

    drawFoods() {
        const minX = this.camera.x - 20;
        const maxX = this.camera.x + this.canvas.width + 20;
        const minY = this.camera.y - 20;
        const maxY = this.camera.y + this.canvas.height + 20;

        for (const [, food] of this.foods) {
            if (food.x < minX || food.x > maxX || food.y < minY || food.y > maxY) {
                continue;
            }

            this.ctx.beginPath();
            this.ctx.arc(food.x, food.y, 8, 0, Math.PI * 2);
            this.ctx.fillStyle = food.color || '#38bdf8';
            this.ctx.fill();
        }
    }

    drawSnakes() {
        for (const [id, snake] of this.snakes) {
            if (!snake.segments || snake.segments.length === 0) continue;

            const isMe = id === this.mySnakeId;

            if (snake.invisible) {
                if (!isMe) continue;
                this.ctx.globalAlpha = 0.35;
            } else {
                this.ctx.globalAlpha = 1.0;
            }

            for (let i = snake.segments.length - 1; i >= 0; i--) {
                const seg = snake.segments[i];
                const isHead = i === 0;

                this.ctx.beginPath();
                this.ctx.arc(seg.x, seg.y, isHead ? 15 : 12, 0, Math.PI * 2);
                this.ctx.fillStyle = isHead ? '#ffffff' : snake.color;
                this.ctx.fill();
            }

            const head = snake.segments[0];

            if (snake.shieldActive && head) {
                this.ctx.save();
                this.ctx.beginPath();
                this.ctx.arc(head.x, head.y, 24, 0, Math.PI * 2);
                this.ctx.fillStyle = 'rgba(56, 189, 248, 0.25)';
                this.ctx.strokeStyle = '#38bdf8';
                this.ctx.lineWidth = 3;
                this.ctx.fill();
                this.ctx.stroke();
                this.ctx.restore();
            }

            this.ctx.font = '12px sans-serif';
            this.ctx.fillStyle = '#ffffff';
            this.ctx.textAlign = 'center';
            this.ctx.fillText(snake.username || '', head.x, head.y - 22);

            this.ctx.globalAlpha = 1.0;
        }
    }
}
