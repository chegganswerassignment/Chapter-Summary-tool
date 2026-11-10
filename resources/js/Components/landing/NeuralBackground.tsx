const NODES = [
    { x: 40, y: 60 },
    { x: 160, y: 30 },
    { x: 280, y: 90 },
    { x: 120, y: 160 },
    { x: 260, y: 200 },
    { x: 380, y: 140 },
    { x: 60, y: 240 },
    { x: 340, y: 40 },
];

const EDGES: [number, number][] = [
    [0, 1],
    [1, 2],
    [1, 3],
    [2, 5],
    [3, 4],
    [4, 5],
    [3, 6],
    [2, 7],
    [5, 7],
];

export default function NeuralBackground() {
    return (
        <svg
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 h-full w-full opacity-[0.35] dark:opacity-[0.5]"
            viewBox="0 0 420 260"
            fill="none"
            preserveAspectRatio="xMidYMid slice"
        >
            <defs>
                <linearGradient id="neuralLine" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stopColor="currentColor" stopOpacity="0.5" />
                    <stop offset="100%" stopColor="currentColor" stopOpacity="0" />
                </linearGradient>
            </defs>
            <g className="text-brand-500">
                {EDGES.map(([a, b], i) => (
                    <line
                        key={i}
                        x1={NODES[a].x}
                        y1={NODES[a].y}
                        x2={NODES[b].x}
                        y2={NODES[b].y}
                        stroke="url(#neuralLine)"
                        strokeWidth="1"
                    />
                ))}
                {NODES.map((node, i) => (
                    <circle
                        key={i}
                        cx={node.x}
                        cy={node.y}
                        r={i % 3 === 0 ? 3.5 : 2}
                        fill="currentColor"
                        className={i % 2 === 0 ? 'animate-pulse' : ''}
                        style={{ animationDuration: `${2.5 + i * 0.4}s` }}
                    />
                ))}
            </g>
        </svg>
    );
}
