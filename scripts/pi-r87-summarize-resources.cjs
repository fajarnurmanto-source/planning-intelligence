const fs=require('node:fs'),path=require('node:path')
const dir=process.argv[2]
const lines=fs.readFileSync(path.join(dir,'r87-php-process-resources.csv'),'utf8').replace(/^\uFEFF/,'').trim().split(/\r?\n/)
const split=s=>[...s.matchAll(/"((?:[^"]|"")*)"/g)].map(m=>m[1].replace(/""/g,'"'))
const keys=split(lines.shift()),rows=lines.map(line=>Object.fromEntries(split(line).map((v,i)=>[keys[i],v])))
const median=a=>{a=a.filter(Number.isFinite).sort((a,b)=>a-b);return a.length?(a[Math.floor((a.length-1)/2)]+a[Math.ceil((a.length-1)/2)])/2:null}
for(const version of ['BEFORE','AFTER']){
 const r=rows.filter(x=>x.version===version),values=k=>r.map(x=>x[k]===''?NaN:Number(x[k])).filter(Number.isFinite)
 const cpu=values('total_cpu_seconds'),ram=values('working_set_mb'),pct=values('cpu_percent_total_machine')
 console.log(JSON.stringify({version,samples:r.length,working_set_median_mb:median(ram),working_set_peak_mb:Math.max(...ram),cpu_seconds_delta:cpu.at(-1)-cpu[0],cpu_machine_percent_median:median(pct),cpu_machine_percent_peak:Math.max(...pct),scope:'PHP process only; includes idle cooldown and requests; 500ms sampling can miss instantaneous peaks'}))
}
