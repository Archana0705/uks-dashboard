/* Chart Loader Styles */
.chart-loader {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100;
    backdrop-filter: blur(2px);
}

.chart-loader .spinner-border {
    border-width: 3px;
}

.chart-loader p {
    font-size: 16px;
    font-weight: 500;
}

/* Chart Container with relative positioning */
#pie {
    position: relative;
}

/* Smooth transitions */
#pie, .chart-loader {
    transition: opacity 0.3s ease;
}

/* Progress indicator animation */
@keyframes pulse {
    0% { opacity: 0.5; }
    50% { opacity: 1; }
    100% { opacity: 0.5; }
}

#chartLoaderProgress {
    animation: pulse 1.5s infinite;
}