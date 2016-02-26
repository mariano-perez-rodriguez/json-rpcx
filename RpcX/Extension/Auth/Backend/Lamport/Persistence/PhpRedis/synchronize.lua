-- ---------------------------------------------------------------------------------------------------------------------
-- Usage: synchronize  <token>  <prefix> <salt> <current>
-- ---------------------------------------------------------------------------------------------------------------------

if #KEYS ~= 1 then return redis.error_reply("ERR faulty dummy in 'lamport.synchronize' script") end

-- extract token
local token = KEYS[1]

-- extract arguments
local prefix  = ARGV[1]
local salt    = ARGV[2]
local current = ARGV[3]

-- extract key names
local dataKey  = prefix .. ':data:{'  .. token .. '}'
local saltsKey = prefix .. ':salts:{' .. token .. '}'

-- fail if unknown salt
if 0 == redis.call('SISMEMBER', saltsKey, salt) then
  redis.error_reply("ERR unknown salt in 'lamport.synchronize'")
-- update current
elseif salt == redis.call('HGET', dataKey, 'salt') then
  redis.call('HSET', dataKey, 'current', current - 1)  -- drop the last one: it was already sent
  return true
-- remove others and update current
else
  local members = redis.call('SMEMBERS', saltsKey)
  local hashesPrefix = prefix .. ':hashes:{' .. token .. '}:'
  for i = 1, #members do
    if members[i] ~= salt then
      redis.call('DEL', hashesPrefix .. members[i])
      redis.call('SREM', saltsKey, members[i])
    else
      local total = redis.call('ZCARD', hashesPrefix .. salt)
      --
      redis.call('HSET', dataKey, 'current', total - 1)  -- drop the last one: it was already sent
      redis.call('HSET', dataKey, 'salt', salt)
    end
  end
  --
  return true
end
