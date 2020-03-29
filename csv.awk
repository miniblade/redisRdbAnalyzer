{
if(NF==8){
    print $3 "," $4
}else{
    tmp="";
    for(i=1;i<=NF;i++){
        if(i<3){continue;}
        if(i>=3&&i<NF-4){
            tmp=sprintf("%s%s%s",tmp,$i,"|");
        }
        if(i==NF-4){
            tmp=sprintf("%s%s%s",tmp,",",$i);
        }
    }
    print tmp;
}
}