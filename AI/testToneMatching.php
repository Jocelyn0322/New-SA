<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>膚色匹配測試</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test { margin: 20px 0; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .pass { background: #e8f5e9; }
        .fail { background: #ffebee; }
        .tone-preview { width: 50px; height: 50px; display: inline-block; margin-left: 10px; border: 1px solid #999; }
    </style>
</head>
<body>
    <h1>膚色匹配測試</h1>
    <div id="results"></div>

    <script>
        async function test() {
            // Load skin tones
            const response = await fetch('getSkinTones.php');
            const tones = await response.json();
            
            const results = [];
            
            // Test cases: [base, depth, bias, expectedToneName]
            const testCases = [
                ['B', '1', 'β', '粉一白'],
                ['B', '2', 'β', '粉二白'],
                ['B', '3', 'β', '粉三白'],
                ['A', '1', 'β', '黃一白'],
                ['C', '1', 'β', '中性冷一白'],
                ['D', '1', 'γ', '橄欖一白'],
            ];
            
            testCases.forEach(([base, depth, bias, expected]) => {
                const found = tones.find(t => t.toneName === expected);
                const status = found ? 'pass' : 'fail';
                
                const result = `
                    <div class="test ${status}">
                        <strong>Base: ${base} | Depth: ${depth} | Bias: ${bias}</strong><br>
                        Expected: ${expected}
                        ${found ? `
                            <br>✓ Found in database
                            <br>Color: ${found.hex} 
                            <div class="tone-preview" style="background-color: ${found.hex}"></div>
                        ` : `
                            <br>✗ NOT found in database
                        `}
                    </div>
                `;
                results.push(result);
            });
            
            document.getElementById('results').innerHTML = results.join('');
        }
        
        test();
    </script>
</body>
</html>
