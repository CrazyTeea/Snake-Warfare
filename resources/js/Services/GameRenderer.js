export class GameRenderer {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.snakes = new Map(); // id -> interpolated snake
        this.targetSnakes = new Map(); // id -> server snake
        this.foods = new Map(); // id -> food
        this.mySnakeId = null;
        this.camera = { x: 0, y: 0 };
        this.mapSize = 5000;
        this.lerpFactor = 0.25; // Коэффициент сглаживания
    }

    setMySnakeId(id) {
        this.mySnakeId = id !== null && id !== undefined ? String(id) : null;
    }

    updateServerState(serverSnakes, eatenFoodIds, newSpawnedFood) {
        const activeIds = new Set();

        for (const snake of serverSnakes) {
            const stringId = String(snake.id);
            activeIds.add(stringId);

            snake.id = stringId;
            this.targetSnakes.set(stringId, snake);

            if (!this.snakes.has(stringId)) {
                this.snakes.set(stringId, JSON.parse(JSON.stringify(snake)));
            }
        }

        for (const [id] of this.snakes) {
            if (!activeIds.has(id)) {
                this.snakes.delete(id);
                this.targetSnakes.delete(id);
            }
        }

        for (const foodId of eatenFoodIds) {
            this.foods.delete(String(foodId));
        }
        for (const food of newSpawnedFood) {
            this.foods.set(String(food.id), food);
        }
    }

    setInitialFoods(foodsArray) {
        this.foods.clear();
        for (const food of foodsArray) {
            this.foods.set(food.id, food);
        }
    }

    interpolate() {
        for (const [id, current] of this.snakes) {
            const target = this.targetSnakes.get(id);
            if (!target) continue;

            current.angle = target.angle;
            current.color = target.color;
            current.shieldActive = Boolean(target.shieldActive);
            current.invisible = Boolean(target.invisible);

            for (let i = 0; i < target.segments.length; i++) {
                if (!current.segments[i]) {
                    current.segments[i] = { ...target.segments[i] };
                    continue;
                }

                current.segments[i].x += (target.segments[i].x - current.segments[i].x) * this.lerpFactor;
                current.segments[i].y += (target.segments[i].y - current.segments[i].y) * this.lerpFactor;
            }

            if (current.segments.length > target.segments.length) {
                current.segments.splice(target.segments.length);
            }
        }

        if (this.mySnakeId && this.snakes.has(this.mySnakeId)) {
            const myHead = this.snakes.get(this.mySnakeId).segments[0];
            if (myHead) {
                this.camera.x = myHead.x - this.canvas.width / 2;
                this.camera.y = myHead.y - this.canvas.height / 2;
            }
        }
    }

    render() {
        this.interpolate();

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.ctx.save();
        this.ctx.translate(-this.camera.x, -this.camera.y);

        this.drawGrid();
        this.drawFoods();
        this.drawSnakes();

        this.ctx.restore();

        this.drawMinimap();
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

            // 👻 Вражеские невидимые змейки не отображаются на миникарте
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
        for (const [, food] of this.foods) {
            this.ctx.beginPath();
            this.ctx.arc(food.x, food.y, 8, 0, Math.PI * 2);
            this.ctx.fillStyle = food.color || '#38bdf8';
            this.ctx.shadowBlur = 10;
            this.ctx.shadowColor = food.color || '#38bdf8';
            this.ctx.fill();
            this.ctx.shadowBlur = 0;
        }
    }

    drawSnakes() {
        for (const [id, snake] of this.snakes) {
            if (!snake.segments || snake.segments.length === 0) continue;

            const isMe = id === this.mySnakeId;

            // 👻 INVISIBLE: Вражеская змейка скрыта, своя отрисовывается с прозрачностью 35%
            if (snake.invisible) {
                if (!isMe) continue;
                this.ctx.globalAlpha = 0.35;
            } else {
                this.ctx.globalAlpha = 1.0;
            }

            // Рисование сегментов тела
            for (let i = snake.segments.length - 1; i >= 0; i--) {
                const seg = snake.segments[i];
                const isHead = i === 0;

                this.ctx.beginPath();
                this.ctx.arc(seg.x, seg.y, isHead ? 15 : 12, 0, Math.PI * 2);
                this.ctx.fillStyle = isHead ? '#ffffff' : snake.color;
                this.ctx.fill();
            }

            const head = snake.segments[0];

            // 🛡️ SHIELD: Аура щита вокруг головы
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

            // Имя игрока над головой
            this.ctx.font = '12px sans-serif';
            this.ctx.fillStyle = '#ffffff';
            this.ctx.textAlign = 'center';
            this.ctx.fillText(snake.username, head.x, head.y - 22);

            this.ctx.globalAlpha = 1.0;
        }
    }
}
